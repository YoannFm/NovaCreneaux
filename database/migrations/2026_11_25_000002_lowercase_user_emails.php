<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Adresses e-mail existantes en minuscules (les nouvelles le sont deja, voir User::email).
 * Deux comptes qui ne different que par les majuscules ne sont pas touches : ils sont signales dans les logs.
 */
return new class extends Migration {
    public function up(): void
    {
        $users = DB::table('users')->select('id', 'email')->get();
        $byLower = $users->groupBy(fn ($user) => mb_strtolower(trim($user->email)));

        foreach ($byLower as $lower => $group) {
            if ($group->count() > 1) {
                Log::warning('Adresses e-mail en double (majuscules) laissees telles quelles', ['ids' => $group->pluck('id')->all()]);
                continue;
            }
            $user = $group->first();
            if ($user->email !== $lower) {
                DB::table('users')->where('id', $user->id)->update(['email' => $lower]);
            }
        }
    }

    public function down(): void
    {
        // Les majuscules d'origine ne sont pas conservees
    }
};
