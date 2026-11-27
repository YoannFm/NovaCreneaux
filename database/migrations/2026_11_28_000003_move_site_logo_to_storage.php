<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Le logo quitte public/uploads/logo pour storage (config nova.logo_directory) : il etait servi directement par le
 * serveur web et restait invisible quand celui-ci ne servait pas ce dossier. Le reglage site_logo ne garde plus que
 * le nom du fichier. Un logo introuvable est oublie (il faudra le renvoyer).
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = (string) DB::table('settings')->where('key', 'site_logo')->value('value');
        if (!str_starts_with($stored, 'uploads/logo/')) {
            return;
        }

        $name = basename($stored);
        $old = public_path('uploads/logo/' . $name);
        $directory = config('nova.logo_directory');
        $moved = preg_match('/^logo_[a-f0-9]{16}\.(jpg|jpeg|png|gif|webp)$/', $name) && is_file($old)
            && (is_dir($directory) || mkdir($directory, 0755, true)) && copy($old, $directory . '/' . $name);
        if ($moved) {
            @unlink($old);
        }

        DB::table('settings')->where('key', 'site_logo')->update(['value' => $moved ? $name : '']);
        Cache::forget('setting_site_logo');
    }

    public function down(): void
    {
        // Le logo reste dans storage : la version precedente ne l'afficherait plus, il suffit de le renvoyer
    }
};
