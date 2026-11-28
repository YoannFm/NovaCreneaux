<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les comptes crees par inscription ou import CSV n'avaient pas de role, donc aucune limite de reservation.
 * On rattache les administrateurs au role admin et tous les autres au role user.
 * Ne touche que les comptes sans role : aucune autre donnee n'est modifiee.
 */
return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $userRoleId = DB::table('roles')->where('name', 'user')->value('id');

        if ($adminRoleId) {
            DB::table('users')->whereNull('role_id')->where('is_admin', true)->update(['role_id' => $adminRoleId]);
        }
        if ($userRoleId) {
            DB::table('users')->whereNull('role_id')->update(['role_id' => $userRoleId]);
        }
    }

    /** Irreversible : on ne sait plus quels comptes n'avaient pas de role. */
    public function down(): void {}
};
