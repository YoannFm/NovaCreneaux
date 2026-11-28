<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Support\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Valide le formulaire d'un webhook et en tire les attributs a enregistrer. Trois formats : charge utile standard,
 * prereglage du catalogue (champs propres au service) et format personnalise (modele de corps et en-tetes).
 */
class WebhookInput
{
    private const MAX_HEADERS = 10;
    /** En-tetes que le webhook ne peut pas fixer : ils decrivent la connexion ou appartiennent a NovaCreneaux. */
    private const FORBIDDEN_HEADERS = ['host', 'content-length', 'content-type', 'transfer-encoding', 'connection', 'upgrade', 'te', 'trailer', 'expect', 'proxy-authorization'];

    public function __construct(
        private PresetCatalog $catalog,
        private UrlGuard $guard,
        private WebhookRequestBuilder $builder,
    ) {}

    /** @return array<string, mixed> attributs du modele Webhook (sans le secret de signature) */
    public function validate(Request $request, ?Webhook $existing = null): array
    {
        $choices = array_merge([Webhook::FORMAT_JSON, Webhook::FORMAT_CUSTOM], array_keys($this->catalog->all()));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:' . implode(',', array_keys(WebhookEvent::all()))],
            'format' => ['sometimes', 'in:' . implode(',', $choices)],
        ]);
        $choice = $data['format'] ?? Webhook::FORMAT_JSON;
        $preset = $this->catalog->find($choice);

        $attributes = [
            'name' => $data['name'],
            'events' => WebhookEvent::only($data['events']),
            'is_active' => $request->boolean('is_active'),
            'format' => $preset ? Webhook::FORMAT_PRESET : $choice,
            'preset' => $preset['id'] ?? null,
        ];

        if ($preset) {
            $attributes += $this->presetAttributes($request, $preset, $existing);
        } else {
            $attributes += ['url' => $this->checkedUrl($request), 'settings' => null];
            if ($choice === Webhook::FORMAT_CUSTOM) {
                $attributes['settings'] = ['custom' => $this->customSettings($request, $existing)];
            }
        }
        $attributes['show_names'] = $choice !== Webhook::FORMAT_JSON && ($preset['body'] ?? null) !== WebhookRequestBuilder::PAYLOAD_BODY && $request->boolean('show_names');

        return $attributes;
    }

    private function checkedUrl(Request $request, ?array $preset = null): string
    {
        $url = trim((string) $request->validate(['url' => ['required', 'string', 'max:500']])['url']);
        $this->checkTarget($url, 'url', $preset);

        return $url;
    }

    private function checkTarget(string $url, string $errorKey, ?array $preset): void
    {
        try {
            $this->guard->check($url);
        } catch (WebhookException $e) {
            throw ValidationException::withMessages([$errorKey => $e->getMessage()]);
        }
        if ($preset && ! PresetCatalog::allowsHost($preset, $url)) {
            throw ValidationException::withMessages([$errorKey => "Avec {$preset['name']}, l'adresse doit être celle du service (" . implode(', ', $preset['hosts']) . ').']);
        }
    }

    /** @return array{url: string, settings: array} */
    private function presetAttributes(Request $request, array $preset, ?Webhook $existing): array
    {
        $kept = $existing?->preset === $preset['id'] ? ($existing->settings['fields'] ?? []) : [];
        $fields = [];
        foreach ($preset['fields'] ?? [] as $field) {
            $key = $field['key'];
            $value = trim((string) $request->input("fields.{$key}", ''));
            // Un secret n'est jamais reaffiche : champ vide = valeur conservee
            if ($value === '' && ($field['secret'] ?? false)) {
                $value = (string) ($kept[$key] ?? '');
            }
            if ($value === '' && ($field['required'] ?? true)) {
                throw ValidationException::withMessages(["fields.{$key}" => "« {$field['label']} » est obligatoire."]);
            }
            if (mb_strlen($value) > 500) {
                throw ValidationException::withMessages(["fields.{$key}" => "« {$field['label']} » est trop long."]);
            }
            $fields[$key] = $value;
        }

        if (($preset['url_template'] ?? null) === null) {
            return ['url' => $this->checkedUrl($request, $preset), 'settings' => ['fields' => $fields]];
        }

        $full = $this->builder->presetUrl($preset, $fields, 'test');
        $this->checkTarget($full, 'fields', $preset);
        // Le chemin de l'adresse peut contenir un jeton : seul le domaine est gardé dans la colonne en clair
        $parts = parse_url($full);

        return ['url' => $parts['scheme'] . '://' . $parts['host'] . '/', 'settings' => ['fields' => $fields]];
    }

    /** @return array{method: string, content_type: string, body: string, headers: array<string, string>} */
    private function customSettings(Request $request, ?Webhook $existing): array
    {
        $custom = $request->validate([
            'custom_method' => ['required', 'in:' . implode(',', PresetCatalog::METHODS)],
            'custom_content_type' => ['required', 'in:' . implode(',', PresetCatalog::CONTENT_TYPES)],
            'custom_body' => ['required', 'string', 'max:5000'],
        ]);
        $contentType = $custom['custom_content_type'];
        $body = str_replace("\r\n", "\n", $custom['custom_body']);

        $template = $body;
        if ($contentType !== 'text') {
            $template = json_decode($body, true);
            $isFlat = is_array($template) && ($contentType !== 'form' || ! array_filter($template, 'is_array'));
            if (! $isFlat) {
                throw ValidationException::withMessages(['custom_body' => $contentType === 'form'
                    ? 'Le corps doit être un objet JSON simple : {"champ": "valeur"}.'
                    : 'Le corps doit être du JSON valide (un objet ou une liste).']);
            }
        }

        $headers = $this->headers($request, $existing?->settings['custom']['headers'] ?? []);
        $this->assertKnownVariables([$template, $headers], 'custom_body');

        return ['method' => $custom['custom_method'], 'content_type' => $contentType, 'body' => $body, 'headers' => $headers];
    }

    /**
     * @param  array<string, string>  $kept  en-tetes deja enregistres (la valeur d'un en-tete n'est jamais reaffichee)
     * @return array<string, string>
     */
    private function headers(Request $request, array $kept): array
    {
        $headers = [];
        $values = (array) $request->input('header_values', []);
        foreach ((array) $request->input('header_names', []) as $i => $rawName) {
            $name = trim((string) $rawName);
            if ($name === '') {
                continue;
            }
            $lower = strtolower($name);
            if (! preg_match('/^[A-Za-z][A-Za-z0-9-]{0,63}$/', $name) || in_array($lower, self::FORBIDDEN_HEADERS, true) || str_starts_with($lower, 'x-nova-')) {
                throw ValidationException::withMessages(['headers' => "En-tête refusé : « {$name} »."]);
            }
            $value = trim((string) ($values[$i] ?? ''));
            if ($value === '') {
                $value = $kept[$name] ?? '';
            }
            if ($value === '' || mb_strlen($value) > 500 || preg_match('/[\r\n]/', $value)) {
                throw ValidationException::withMessages(['headers' => "La valeur de l'en-tête « {$name} » est manquante ou invalide."]);
            }
            $headers[$name] = $value;
        }
        if (count($headers) > self::MAX_HEADERS) {
            throw ValidationException::withMessages(['headers' => 'Dix en-têtes au plus.']);
        }

        return $headers;
    }

    private function assertKnownVariables(array $templates, string $errorKey): void
    {
        $unknown = array_diff(TemplateRenderer::variablesIn($templates), array_keys(EventMessage::VARIABLES));
        if ($unknown !== []) {
            throw ValidationException::withMessages([$errorKey => 'Variable inconnue : {{ ' . implode(' }}, {{ ', $unknown) . ' }}.']);
        }
    }
}
