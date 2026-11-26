<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Parametres > General : la langue et les mots-cles n'ont jamais eu d'effet, ils sont retires. La description du
 * site devient du texte simple (balise meta "description") : un ancien contenu saisi dans l'editeur perd son HTML.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->whereIn('key', ['locale', 'site_keywords'])->delete();

        $description = DB::table('settings')->where('key', 'site_description')->value('value');
        if ($description !== null) {
            $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            DB::table('settings')->where('key', 'site_description')->update(['value' => mb_substr($plain, 0, 300)]);
        }

        foreach (['locale', 'site_keywords', 'site_description'] as $key) {
            Cache::forget("setting_{$key}");
        }
    }

    public function down(): void
    {
        // Rien a remettre : les reglages retires n'avaient aucun effet
    }
};
