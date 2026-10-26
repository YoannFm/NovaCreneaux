<?php

namespace Tests\Feature\Admin;

use App\Services\SettingService;
use App\Support\Permission;
use App\Support\ThemeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class ThemeSettingsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function palette(array $override = []): array
    {
        return $override + ThemeSettings::defaults();
    }

    public function test_the_form_lists_every_color_of_both_modes_with_its_current_value(): void
    {
        app(SettingService::class)->set('theme_light_card_bg', '#fafafa');

        $page = $this->actingAs($this->admin())->get(route('admin.themes.index'))->assertOk();

        foreach (array_keys(ThemeSettings::defaults()) as $key) {
            $page->assertSee('name="' . $key . '"', false);
        }
        $page->assertSee('value="#fafafa"', false);
    }

    public function test_valid_colors_are_saved_and_applied_to_the_layouts(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.themes.update'), $this->palette(['theme_accent' => '#123456', 'theme_body_bg' => '#0a0b0c']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('#123456', setting('theme_accent'));
        $css = $this->get(route('admin.themes.index'))->getContent();
        $this->assertMatchesRegularExpression('/--accent:\s+#123456;/', $css);
        $this->assertMatchesRegularExpression('/--body-bg:\s+#0a0b0c;/', $css);
    }

    public function test_an_invalid_color_rejects_the_whole_form(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.themes.update'), $this->palette(['theme_accent' => '#123456', 'theme_card_bg' => 'rouge']))
            ->assertSessionHasErrors('theme_card_bg');

        $this->assertNotSame('#123456', setting('theme_accent'));
    }

    public function test_a_missing_color_is_rejected(): void
    {
        $payload = $this->palette();
        unset($payload['theme_text_color']);

        $this->actingAs($this->admin())->put(route('admin.themes.update'), $payload)->assertSessionHasErrors('theme_text_color');
    }

    public function test_defaults_apply_until_something_is_saved(): void
    {
        $this->assertSame('#efb703', ThemeSettings::value('theme_accent'));
        $this->assertSame('#111113', ThemeSettings::value('theme_body_bg'));
    }

    public function test_the_appearance_permission_is_enough_to_change_colors(): void
    {
        $staff = $this->staff([Permission::APPEARANCE]);

        $this->actingAs($staff)->get(route('admin.themes.index'))->assertOk();
        $this->put(route('admin.themes.update'), $this->palette(['theme_accent' => '#abcdef']))->assertSessionHasNoErrors();
        $this->assertSame('#abcdef', setting('theme_accent'));
    }
}
