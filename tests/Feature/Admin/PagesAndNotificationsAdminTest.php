<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Ces actions plantaient (erreur 500 apres avoir enregistre) a cause d'une classe de journal absente. */
class PagesAndNotificationsAdminTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_pages_can_be_created_updated_and_deleted_without_error_and_are_logged_once_each(): void
    {
        $this->actingAs($this->admin());
        ActivityLog::query()->delete();

        $this->post(route('admin.pages.store'), ['title' => 'Règlement', 'slug' => 'reglement', 'content' => '<p>x</p>'])->assertRedirect(route('admin.pages.index'));
        $page = Page::sole();
        $this->put(route('admin.pages.update', $page), ['title' => 'Règlement 2', 'slug' => 'reglement', 'content' => '<p>y</p>'])->assertRedirect();
        $this->delete(route('admin.pages.destroy', $page))->assertRedirect();

        $this->assertSame(['Page créée', 'Page modifiée', 'Page supprimée'], ActivityLog::orderBy('id')->pluck('action')->all());
    }

    public function test_notifications_can_be_sent_and_deleted_without_error_and_are_logged(): void
    {
        $pupil = $this->student();
        $this->actingAs($this->admin());

        $this->post(route('admin.notifications.store'), ['title' => 'Fermeture', 'level' => 'info', 'target' => 'users', 'user_ids' => [$pupil->id]])
            ->assertRedirect(route('admin.notifications.index'));
        $this->delete(route('admin.notifications.destroy', UserNotification::sole()))->assertRedirect();

        $this->assertSame(
            ['Notification envoyée' => '« Fermeture » : 1 destinataire(s)', 'Notification supprimée' => '« Fermeture »'],
            ActivityLog::orderBy('id')->pluck('context', 'action')->all(),
        );
    }
}
