<?php

namespace App\Services\Sso;

use App\Support\SsoSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Support\RequestText;

/**
 * OpenID Connect, flux "authorization code" avec PKCE (S256), state et nonce. Le jeton d'identite est recu
 * directement du point de terminaison du fournisseur en https : sa signature n'a pas a etre verifiee (OpenID
 * Connect Core 3.1.3.7), mais son emetteur, son destinataire, son nonce et son expiration le sont.
 */
class OidcProvider implements SsoProvider
{
    private const TIMEOUT = 10;

    public function redirectUrl(string $callback, array $state): string
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => setting('oidc_client_id'),
            'redirect_uri' => $callback,
            'scope' => SsoSettings::oidcScopes(),
            'state' => $state['state'],
            'nonce' => $state['nonce'],
            'code_challenge' => self::base64Url(hash('sha256', $state['verifier'], true)),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
        $endpoint = $this->discovery()['authorization_endpoint'];

        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . $query;
    }

    public function identity(Request $request, string $callback, array $state): SsoIdentity
    {
        if ($request->filled('error')) {
            throw new SsoException('Connexion refusée par le service de l\'établissement : ' . mb_substr(RequestText::get($request, 'error_description', RequestText::get($request, 'error')), 0, 200));
        }
        $code = RequestText::get($request, 'code');
        if ($code === '') {
            throw new SsoException('Réponse incomplète du service de l\'établissement.');
        }

        $discovery = $this->discovery();
        $tokens = $this->tokens($discovery, $code, $callback, $state['verifier']);
        $claims = $this->idTokenClaims($tokens['id_token'] ?? '', $discovery['issuer'], $state['nonce']);
        if (empty($claims['email']) && !empty($discovery['userinfo_endpoint']) && !empty($tokens['access_token'])) {
            // Le jeton d'identite prime ; userinfo complete ce qu'il ne dit pas
            $userinfo = $this->get(Http::withToken($tokens['access_token']), $discovery['userinfo_endpoint'])->json() ?? [];
            $claims = array_merge($userinfo, array_filter($claims, fn ($value) => $value !== null));
        }

        if (($claims['email_verified'] ?? true) === false) {
            throw new SsoException("Le service de l'établissement n'a pas vérifié cette adresse e-mail.");
        }
        $email = (string) ($claims['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SsoException("Le service de l'établissement n'a pas transmis d'adresse e-mail (portée \"email\" à demander).");
        }
        $name = $claims['name'] ?? trim(($claims['given_name'] ?? '') . ' ' . ($claims['family_name'] ?? ''));

        return new SsoIdentity($email, $name !== '' ? (string) $name : null);
    }

    /** Document de decouverte du fournisseur (mis en cache une heure). */
    private function discovery(): array
    {
        $issuer = rtrim((string) setting('oidc_issuer'), '/');

        return Cache::remember('sso-oidc-discovery:' . md5($issuer), 3600, function () use ($issuer) {
            $document = $this->get(Http::acceptJson(), $issuer . '/.well-known/openid-configuration')->json();
            foreach (['issuer', 'authorization_endpoint', 'token_endpoint'] as $key) {
                if (!is_string($document[$key] ?? null) || ($key !== 'issuer' && !str_starts_with($document[$key], 'https://'))) {
                    throw $this->failure("document de découverte sans {$key} valide");
                }
            }
            if (rtrim($document['issuer'], '/') !== $issuer) {
                throw $this->failure("émetteur annoncé ({$document['issuer']}) différent de celui configuré");
            }

            return $document;
        });
    }

    private function tokens(array $discovery, string $code, string $callback, string $verifier): array
    {
        $form = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $callback, 'code_verifier' => $verifier];
        $clientId = (string) setting('oidc_client_id');
        $secret = (string) setting('oidc_client_secret', '');
        $methods = $discovery['token_endpoint_auth_methods_supported'] ?? ['client_secret_basic'];
        $http = Http::asForm();

        // Identification du site aupres du fournisseur : client_secret_basic par defaut (OpenID Connect), sinon post
        if ($secret !== '' && in_array('client_secret_basic', $methods, true)) {
            $http = $http->withBasicAuth(rawurlencode($clientId), rawurlencode($secret));
        } else {
            $form += ['client_id' => $clientId] + ($secret !== '' ? ['client_secret' => $secret] : []);
        }

        return $this->send(fn () => $http->timeout(self::TIMEOUT)->post($discovery['token_endpoint'], $form))->json() ?? [];
    }

    private function idTokenClaims(string $idToken, string $issuer, string $nonce): array
    {
        $parts = explode('.', $idToken);
        $claims = count($parts) === 3 ? json_decode((string) self::base64UrlDecode($parts[1]), true) : null;
        if (!is_array($claims)) {
            throw $this->failure('jeton d\'identité absent ou illisible');
        }

        $audience = (array) ($claims['aud'] ?? []);
        $problem = match (true) {
            ($claims['iss'] ?? null) !== $issuer => 'émetteur du jeton inattendu',
            !in_array(setting('oidc_client_id'), $audience, true) => 'jeton destiné à une autre application',
            !hash_equals($nonce, (string) ($claims['nonce'] ?? '')) => 'nonce du jeton différent',
            (int) ($claims['exp'] ?? 0) < time() - 60 => 'jeton expiré',
            default => null,
        };
        if ($problem !== null) {
            throw $this->failure($problem);
        }

        return $claims;
    }

    private function get(PendingRequest $http, string $url): Response
    {
        return $this->send(fn () => $http->timeout(self::TIMEOUT)->get($url));
    }

    private function send(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw $this->failure('service injoignable : ' . $e->getMessage());
        }
        if (!$response->successful()) {
            throw $this->failure("réponse HTTP {$response->status()} : " . mb_substr($response->body(), 0, 300));
        }

        return $response;
    }

    /** Le detail technique va dans les logs ; la personne voit un message simple. */
    private function failure(string $detail): SsoException
    {
        Log::warning("Connexion OpenID Connect : {$detail}");

        return new SsoException("La connexion avec le compte de l'établissement a échoué. Réessayez ou utilisez votre mot de passe.");
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
