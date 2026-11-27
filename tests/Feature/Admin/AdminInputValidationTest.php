<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Formulaire des pages et filtres des listes : une saisie invalide donne une erreur de validation, jamais une erreur 500. */
class AdminInputValidationTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function page(array $fields = [])
    {
        return $this->post(route('admin.pages.store'), $fields + ['title' => 'Règlement', 'slug' => 'reglement', 'content' => '<p>Texte</p>', 'is_enabled' => '1']);
    }

    public function test_two_addresses_with_the_same_normal_form_are_refused(): void
    {
        $this->actingAs($this->admin());
        $this->page()->assertSessionHasNoErrors();

        $this->page(['slug' => 'Règlement'])->assertSessionHasErrors(['slug' => 'Cette adresse est déjà utilisée par une autre page.']);

        $this->assertSame(1, Page::count());
        $this->put(route('admin.pages.update', Page::first()), ['title' => 'Règlement', 'slug' => 'Reglement', 'content' => '<p>Texte</p>'])
            ->assertSessionHasNoErrors();
    }

    public function test_an_address_without_letters_or_digits_is_refused(): void
    {
        $this->actingAs($this->admin());

        $this->page(['slug' => '!!!'])->assertSessionHasErrors(['slug' => "L'adresse doit contenir au moins une lettre ou un chiffre."]);
        $this->page(['slug' => ['tableau']])->assertSessionHasErrors('slug');

        $this->assertSame(0, Page::count());
    }

    public function test_an_unknown_role_is_refused_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin());

        $this->page(['restricted' => '1', 'roles' => [9999]])->assertSessionHasErrors('roles.0');
        $this->assertSame(0, Page::count());

        $this->page(['restricted' => '1', 'roles' => [Role::defaultUser()->id]])->assertSessionHasNoErrors();
        $this->assertSame([Role::defaultUser()->id], Page::first()->roles->modelKeys());
    }

    public function test_an_invalid_list_filter_is_ignored_without_redirecting(): void
    {
        $this->actingAs($this->admin());

        foreach (['/admin/logs?from[]=x', '/admin/logs?from=abc', '/admin/users?search[]=x', '/admin/users?role=abc', '/admin/reservations?date=2026-02-30'] as $url) {
            // Deux fois de suite (lien, favori, rechargement) : jamais de redirection vers la meme adresse, donc pas de boucle
            $this->get($url)->assertOk()->assertSee("Un filtre de l'adresse n'est pas valide : il a été ignoré.");
            $this->get($url)->assertOk();
        }
        $this->getJson(route('admin.reservations.users', ['q' => ['lea']]))->assertOk()->assertExactJson([]);
        auth()->logout();
        $this->get(route('sso.callback', ['state' => ['x'], 'code' => 'y']))->assertRedirect(route('login'));
        $this->actingAs($this->admin());
        $this->get('/admin/logs?from=2026-11-01&user=admin')->assertOk()->assertDontSee("Un filtre de l'adresse n'est pas valide");
        $this->get('/admin/users?search=lea&role=1')->assertOk()->assertDontSee("Un filtre de l'adresse n'est pas valide");
    }

    public function test_a_long_activity_context_is_shortened_instead_of_lost(): void
    {
        ActivityLogger::log(Str::repeat('a', 200), Str::repeat('é', 800));

        $log = ActivityLog::latest('id')->firstOrFail();
        $this->assertSame(150, mb_strlen($log->action));
        $this->assertSame(500, mb_strlen($log->context));
    }
}
