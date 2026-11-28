<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $defaults = [
            'theme_accent'      => '#efb703',
            'theme_body_bg'     => '#111113',
            'theme_card_bg'     => '#212227',
            'theme_header_bg'   => '#1a1b1f',
            'theme_text_color'  => '#e2e8f0',
            // light mode variants
            'theme_light_body_bg'    => '#f4f5f7',
            'theme_light_card_bg'    => '#ffffff',
            'theme_light_header_bg'  => '#ffffff',
            'theme_light_text_color' => '#1a1a2e',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => $value]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'theme_accent','theme_body_bg','theme_card_bg','theme_header_bg','theme_text_color',
            'theme_light_body_bg','theme_light_card_bg','theme_light_header_bg','theme_light_text_color',
        ])->delete();
    }
};
