<?php

namespace App\Services\Configuration;

use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Services\ActivityLogger;
use App\Services\SettingService;
use App\Support\ActivityChanges;
use App\Support\Permission;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Applique un fichier de configuration (ConfigurationFile) en fusionnant : un role se retrouve par son
 * identifiant, une ressource par son nom, un creneau par ses horaires dans sa ressource. Ce qui existe est mis a
 * jour, ce qui manque est cree, rien n'est jamais supprime (aucune reservation ne peut etre perdue).
 * Un role administrateur n'est jamais modifie et un role importe ne devient jamais administrateur.
 * Tout ou rien : une erreur annule l'import entier.
 */
class ConfigurationImporter
{
    public function __construct(private SettingService $settings) {}

    /** @throws \Illuminate\Validation\ValidationException */
    public function import(mixed $data, string $source): ImportReport
    {
        $data = ConfigurationFile::validate($data);
        $report = new ImportReport();

        DB::transaction(function () use ($data, $report, $source) {
            $before = $this->settings->snapshot();
            $this->importSettings($data['settings'] ?? [], $report);
            foreach ($data['roles'] ?? [] as $role) {
                $this->importRole($role, $report);
            }
            foreach ($data['resources'] ?? [] as $resource) {
                $this->importResource($resource, $report);
            }
            $changes = ActivityChanges::diff($before, $this->settings->snapshot(), SettingService::SECRET_KEYS);
            $warnings = $report->warnings() ? ' ; attention : ' . implode(' ; ', $report->warnings()) : '';
            ActivityLogger::log('Configuration importée', "{$source} : {$report->summary()}{$warnings}", $changes);
        });

        return $report;
    }

    private function importSettings(array $settings, ImportReport $report): void
    {
        foreach (array_intersect_key($settings, ConfigurationFile::settings()) as $key => $value) {
            if ((string) setting($key, '') !== (string) $value) {
                $this->settings->set($key, (string) $value);
                $report->record('setting', 'updated');
            }
        }
    }

    private function importRole(array $input, ImportReport $report): void
    {
        $role = Role::where('name', $input['name'])->first();
        if ($role?->is_admin) {
            $report->skip("rôle administrateur « {$role->label} » laissé tel quel");

            return;
        }

        $values = Arr::only($input, ConfigurationFile::roleFields());
        if (array_key_exists('permissions', $values)) {
            $values['permissions'] = Permission::only($values['permissions'] ?? []);
        }
        if (array_key_exists('api_access', $values)) {
            $values['api_access'] = (bool) $values['api_access'];
        }
        if ($role === null || array_key_exists('icon', $values)) {
            $values['icon'] = ($values['icon'] ?? null) ?: Role::DEFAULT_ICON;
        }

        if ($role === null) {
            Role::create($values + ['name' => $input['name'], 'label' => $input['name'], 'color' => Role::DEFAULT_COLOR,
                'is_admin' => false, 'locked' => false, 'power' => 0] + Role::defaultLimits());
            $report->record('role', 'created');
        } elseif ($role->fill($values)->isDirty()) {
            // Des permissions donnees a un role qui a des comptes les font entrer dans l'administration : le signaler
            $opensAdmin = $role->grantsAdminAccess() && !Role::find($role->id)->grantsAdminAccess();
            $role->save();
            $report->record('role', 'updated');
            if ($opensAdmin && ($accounts = $role->users()->count()) > 0) {
                $report->warn("le rôle « {$role->label} » donne maintenant accès à l'administration à {$accounts} compte(s)");
            }
        }
    }

    private function importResource(array $input, ImportReport $report): void
    {
        $values = Arr::only($input, ['description', 'open_time', 'close_time', 'closed_days']);
        if (array_key_exists('is_active', $input)) {
            $values['is_active'] = (bool) $input['is_active'];
        }

        $resource = Resource::firstOrNew(['name' => $input['name']]);
        $created = !$resource->exists;
        $resource->fill($values + ($created ? ['is_active' => true, 'closed_days' => []] : []));
        if ($resource->isDirty()) {
            $resource->save();
            $report->record('resource', $created ? 'created' : 'updated');
        }

        $existing = $resource->slots()->get();
        foreach ($input['slots'] ?? [] as $slotInput) {
            $slot = $existing->first(fn (Slot $slot) => substr($slot->start_time, 0, 5) === $slotInput['start_time']
                && substr($slot->end_time, 0, 5) === $slotInput['end_time']);
            $this->importSlot($resource, $slot, $slotInput, $report);
        }
    }

    private function importSlot(Resource $resource, ?Slot $slot, array $input, ImportReport $report): void
    {
        $values = Arr::only($input, ['name', 'max_capacity']);
        if (array_key_exists('is_active', $input)) {
            $values['is_active'] = (bool) $input['is_active'];
        }

        if ($slot === null) {
            $resource->slots()->create($values + Arr::only($input, ['start_time', 'end_time']) + ['is_active' => true]);
            $report->record('slot', 'created');
        } elseif ($slot->fill($values)->isDirty()) {
            $slot->save();
            $report->record('slot', 'updated');
        }
    }
}
