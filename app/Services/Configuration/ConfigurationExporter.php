<?php

namespace App\Services\Configuration;

use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Support\Permission;

/** Fichier de configuration du site (voir ConfigurationFile) : jamais de secret, de compte ni de reservation. */
class ConfigurationExporter
{
    public function export(): array
    {
        return [
            'format' => ConfigurationFile::FORMAT,
            'version' => ConfigurationFile::VERSION,
            'exported_from' => config('nova.version'),
            'exported_at' => now()->toIso8601String(),
            'settings' => $this->settings(),
            // Les roles administrateurs ne voyagent pas : l'import ne les modifie jamais
            'roles' => Role::where('is_admin', false)->orderByDesc('power')->orderBy('id')->get()
                ->map(fn (Role $role) => array_merge(['name' => $role->name], $role->only(ConfigurationFile::roleFields()),
                    ['permissions' => Permission::only($role->permissions ?? [])]))
                ->values()->all(),
            'resources' => Resource::with(['slots' => fn ($query) => $query->orderBy('start_time')])->orderBy('name')->get()
                ->map(fn (Resource $resource) => $this->resource($resource))->values()->all(),
        ];
    }

    private function settings(): array
    {
        $settings = [];
        foreach (array_keys(ConfigurationFile::settings()) as $key) {
            $value = (string) setting($key, '');
            if ($value !== '') {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    private function resource(Resource $resource): array
    {
        return [
            'name' => $resource->name,
            'description' => $resource->description,
            'is_active' => $resource->is_active,
            'open_time' => substr((string) $resource->open_time, 0, 5),
            'close_time' => substr((string) $resource->close_time, 0, 5),
            'closed_days' => array_values($resource->closed_days ?? []),
            'slots' => $resource->slots->map(fn (Slot $slot) => [
                'name' => $slot->name,
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'max_capacity' => $slot->max_capacity,
                'is_active' => $slot->is_active,
            ])->values()->all(),
        ];
    }
}
