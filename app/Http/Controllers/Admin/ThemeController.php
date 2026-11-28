<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use App\Support\ThemeSettings;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function index()
    {
        return view('admin.themes.index', [
            'dark' => ThemeSettings::fields(ThemeSettings::DARK),
            'light' => ThemeSettings::fields(ThemeSettings::LIGHT),
        ]);
    }

    public function update(Request $request, SettingService $settings)
    {
        $colors = $request->validate(ThemeSettings::rules());

        foreach ($colors as $key => $value) {
            $settings->set($key, $value);
        }

        return back()->with('success', 'Thème mis à jour.');
    }
}
