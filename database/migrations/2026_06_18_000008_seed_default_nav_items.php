<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // N'insère que si la table est vide
        if (DB::table('nav_items')->count() > 0) return;

        DB::table('nav_items')->insert([
            [
                'label'      => 'Accueil',
                'type'       => 'home',
                'url'        => '',
                'icon'       => 'bi-house',
                'target'     => '_self',
                'is_active'  => true,
                'admin_only' => false,
                'position'   => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label'      => 'Réserver',
                'type'       => 'reservations',
                'url'        => '',
                'icon'       => 'bi-calendar-plus',
                'target'     => '_self',
                'is_active'  => true,
                'admin_only' => false,
                'position'   => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label'      => 'Mes réservations',
                'type'       => 'my_reservations',
                'url'        => '',
                'icon'       => 'bi-bookmark-check',
                'target'     => '_self',
                'is_active'  => true,
                'admin_only' => false,
                'position'   => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('nav_items')->whereIn('type', ['home', 'reservations', 'my_reservations'])->delete();
    }
};
