<?php

namespace Tests\Support;

use App\Models\Role;
use App\Models\User;

/** Comptes de test pour l'administration : administrateur, eleve et personnel delegue. */
trait CreatesStaff
{
    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
    }

    private function student(): User
    {
        return User::factory()->create(['role_id' => Role::defaultUser()->id]);
    }

    /** @param list<string> $permissions */
    private function staff(array $permissions, string $name = 'documentaliste'): User
    {
        $role = Role::firstOrCreate(['name' => $name], [
            'label' => ucfirst($name), 'icon' => 'bi bi-star', 'color' => '#3b7ddd', 'is_admin' => false, 'locked' => false,
        ]);
        $role->update(['permissions' => $permissions]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
