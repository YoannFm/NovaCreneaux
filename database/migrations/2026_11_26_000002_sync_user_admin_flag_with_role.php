<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * users.is_admin recopie le statut d'administrateur du role (le role fait foi, voir User::isAdmin). Un role dont on
 * avait retire ce statut laissait la colonne a vrai pour ses comptes : on l'aligne sur le role.
 */
return new class extends Migration
{
    public function up(): void
    {
        $adminRoles = DB::table('roles')->where('is_admin', true)->pluck('id');
        $stale = DB::table('users')->whereNotNull('role_id')->whereNotIn('role_id', $adminRoles)->where('is_admin', true);

        $emails = (clone $stale)->pluck('email')->all();
        if ($emails !== []) {
            Log::warning("Statut d'administrateur retire : leur role ne l'est plus.", ['emails' => $emails]);
        }
        $stale->update(['is_admin' => false]);
        DB::table('users')->whereIn('role_id', $adminRoles)->update(['is_admin' => true]);
    }

    public function down(): void
    {
        // Rien a remettre : la colonne n'est qu'une copie du role
    }
};
