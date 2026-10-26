<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function index()
    {
        return view('admin.themes.index');
    }

    public function update(Request $request)
    {
        $request->validate([
            'theme_accent'           => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_body_bg'          => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_card_bg'          => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_header_bg'        => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_text_color'       => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_light_body_bg'    => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_light_card_bg'    => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_light_header_bg'  => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_light_text_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $keys = [
            'theme_accent','theme_body_bg','theme_card_bg','theme_header_bg','theme_text_color',
            'theme_light_body_bg','theme_light_card_bg','theme_light_header_bg','theme_light_text_color',
        ];

        foreach ($keys as $key) {
            app(SettingService::class)->set($key, $request->input($key));
        }

        return back()->with('success', 'Theme mis a jour.');
    }
}
