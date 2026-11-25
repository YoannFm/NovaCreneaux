<?php

namespace App\Services\Configuration;

use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Support\Customization;
use App\Support\ReservationLimits;
use App\Support\ThemeSettings;
use Illuminate\Support\Facades\Validator;

/**
 * Format d'un fichier de configuration (export, import et modeles prets a importer) :
 * {"format", "version", "title"?, "icon"?, "description"?, "settings"?, "roles"?, "resources"? (avec leurs "slots")}.
 *
 * Seuls les reglages de SETTINGS voyagent : textes de l'etablissement et couleurs. Liste blanche volontaire :
 * un secret (mot de passe SMTP, jetons) ou un reglage propre a une installation (adresse, proxys, securite)
 * ne peut jamais partir dans un fichier, meme s'il est ajoute plus tard.
 */
final class ConfigurationFile
{
    public const FORMAT = 'novacreneaux-configuration';
    public const VERSION = 1;

    private const MAX_ROLES = 50;
    private const MAX_RESOURCES = 100;
    private const MAX_SLOTS = 100;

    /** @return array<string, string|array> reglage => regle de validation */
    public static function settings(): array
    {
        return Customization::rules() + array_map(fn () => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], ThemeSettings::defaults());
    }

    /** Champs exportes d'un role, en plus de "name". */
    public static function roleFields(): array
    {
        return ['label', 'icon', 'description', 'color', 'api_access', 'permissions', ...array_keys(ReservationLimits::fields())];
    }

    /**
     * Verifie un fichier decode et renvoie ses donnees validees.
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validate(mixed $data): array
    {
        $rules = [
            'format' => 'required|in:' . self::FORMAT,
            'version' => 'required|integer|in:' . self::VERSION,
            'title' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:500',
            'settings' => 'nullable|array',
            'roles' => 'nullable|array|max:' . self::MAX_ROLES,
            'roles.*.name' => Role::NAME_RULE,
            'roles.*.description' => 'nullable|string|max:500',
            'resources' => 'nullable|array|max:' . self::MAX_RESOURCES,
            'resources.*.slots' => 'nullable|array|max:' . self::MAX_SLOTS,
        ] + Role::rules('roles.*.', partial: true) + Resource::rules('resources.*.') + Slot::rules('resources.*.slots.*.');

        foreach (self::settings() as $key => $rule) {
            $rules["settings.{$key}"] = $rule;
        }
        foreach (ReservationLimits::fields() as $key => $field) {
            $rules["roles.*.{$key}"] = ['sometimes', function (string $attribute, mixed $value, \Closure $fail) use ($key, $field) {
                if (!ReservationLimits::accepts($key, $value)) {
                    $fail("{$field['label']} : valeur refusée.");
                }
            }];
        }

        $validator = Validator::make(is_array($data) ? $data : [], $rules, [
            'format.required' => "Ce fichier n'est pas une configuration de NovaCreneaux.",
            'format.in' => "Ce fichier n'est pas une configuration de NovaCreneaux.",
            'version.in' => "Cette configuration vient d'une version plus récente : mettez d'abord le site à jour.",
        ]);

        return $validator->validate();
    }
}
