<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterAndLicenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_licence_file_is_the_standard_mit_licence(): void
    {
        $licence = file_get_contents(base_path('LICENSE'));

        $this->assertStringStartsWith('MIT License', $licence);
        $this->assertStringContainsString('Copyright (c) 2026 LE BORGNE Yoann', $licence);
        // Texte MIT exact, sans ligne ajoutee : sinon GitHub ne reconnait pas la licence (badge "not identifiable")
        $this->assertStringNotContainsString('Date :', $licence);
        $this->assertStringContainsString('Permission is hereby granted, free of charge', $licence);
        $this->assertStringNotContainsString('Propriétaire', $licence);
    }

    public function test_both_footers_link_to_the_public_repository_and_show_the_licence_from_the_file(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);

        foreach ([route('reservations.index'), route('admin.dashboard')] as $url) {
            $page = $this->actingAs($admin)->get($url)->assertOk();
            $page->assertSee('https://github.com/' . config('nova.repo'), false)
                ->assertSee('Licence MIT')
                ->assertSee('Permission is hereby granted, free of charge', false)
                ->assertDontSee('Tous droits réservés')
                ->assertDontSee('Licence Propriétaire')
                ->assertDontSee('wiki.novadev.ovh/', false);
        }
    }
}
