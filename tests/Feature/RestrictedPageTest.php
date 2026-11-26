<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Role;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Page reservee a certains roles : ceux-ci la voient, et celui qui gere les pages aussi (pour la verifier). */
class RestrictedPageTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private function page(array $roleIds): Page
    {
        $page = Page::create(['title' => 'Règlement', 'slug' => 'reglement', 'content' => '<p>Texte du règlement</p>', 'is_enabled' => true, 'is_restricted' => true]);
        $page->roles()->sync($roleIds);

        return $page;
    }

    public function test_only_the_chosen_roles_and_the_page_managers_see_it(): void
    {
        $other = Role::create(['name' => 'professeur', 'label' => 'Professeur', 'color' => '#123456']);
        $this->page([Role::defaultUser()->id]);
        $url = route('pages.show', 'reglement');

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->student())->get($url)->assertOk()->assertSee('Texte du règlement');
        $this->actingAs($this->student(['role_id' => $other->id]))->get($url)->assertForbidden();
        $this->actingAs($this->staff([Permission::RESERVATIONS], 'surveillant'))->get($url)->assertForbidden();

        $this->actingAs($this->admin())->get($url)->assertOk()->assertSee('Texte du règlement');
        $this->actingAs($this->staff([Permission::PAGES]))->get($url)->assertOk()->assertSee('Texte du règlement');
    }

    public function test_without_any_role_chosen_every_signed_in_account_sees_it(): void
    {
        $this->page([]);

        $this->actingAs($this->student())->get(route('pages.show', 'reglement'))->assertOk();
    }
}
