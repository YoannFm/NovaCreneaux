<?php

namespace App\Services\Sso;

use App\Support\SsoSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CAS (protocole 3) : le serveur renvoie un ticket, valide ensuite par /p3/serviceValidate. Le "state" de la
 * tentative fait partie de l'adresse de retour (service) : un ticket obtenu pour une autre tentative est refuse.
 */
class CasProvider implements SsoProvider
{
    private const NS = 'http://www.yale.edu/tp/cas';

    public function redirectUrl(string $callback, array $state): string
    {
        return $this->base() . '/login?' . http_build_query(['service' => $this->service($callback, $state)], '', '&', PHP_QUERY_RFC3986);
    }

    public function identity(Request $request, string $callback, array $state): SsoIdentity
    {
        $ticket = (string) $request->input('ticket');
        if ($ticket === '') {
            throw new SsoException('Réponse incomplète du service de l\'établissement.');
        }

        try {
            $response = Http::timeout(10)->get($this->base() . '/p3/serviceValidate', ['service' => $this->service($callback, $state), 'ticket' => $ticket]);
        } catch (ConnectionException $e) {
            throw $this->failure('service injoignable : ' . $e->getMessage());
        }
        if (!$response->successful()) {
            throw $this->failure("réponse HTTP {$response->status()}");
        }

        return $this->parse($response->body());
    }

    /** Lit la reponse XML de serviceValidate (sans entite externe ni acces reseau). */
    private function parse(string $xml): SsoIdentity
    {
        $document = new \DOMDocument();
        if ($xml === '' || !@$document->loadXML($xml, LIBXML_NONET)) {
            throw $this->failure('réponse XML illisible');
        }
        $success = $document->getElementsByTagNameNS(self::NS, 'authenticationSuccess')->item(0);
        if ($success === null) {
            $failure = $document->getElementsByTagNameNS(self::NS, 'authenticationFailure')->item(0);
            throw $this->failure('ticket refusé' . ($failure ? ' : ' . trim($failure->textContent) : ''));
        }

        $user = trim((string) $success->getElementsByTagNameNS(self::NS, 'user')->item(0)?->textContent);
        $email = $this->attribute($success, SsoSettings::casEmailAttribute()) ?? $user;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SsoException("Le service de l'établissement n'a pas transmis d'adresse e-mail (attribut \"" . SsoSettings::casEmailAttribute() . '").');
        }
        $name = $this->attribute($success, 'displayName') ?? $this->attribute($success, 'cn');

        return new SsoIdentity($email, $name);
    }

    private function attribute(\DOMElement $success, string $name): ?string
    {
        $value = trim((string) $success->getElementsByTagNameNS(self::NS, $name)->item(0)?->textContent);

        return $value !== '' ? $value : null;
    }

    private function service(string $callback, array $state): string
    {
        return $callback . '?' . http_build_query(['state' => $state['state']]);
    }

    private function base(): string
    {
        return rtrim((string) setting('cas_url'), '/');
    }

    private function failure(string $detail): SsoException
    {
        Log::warning("Connexion CAS : {$detail}");

        return new SsoException("La connexion avec le compte de l'établissement a échoué. Réessayez ou utilisez votre mot de passe.");
    }
}
