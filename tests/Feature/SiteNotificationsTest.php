<?php

namespace Tests\Feature;

use App\Models\UserNotification;
use App\Services\SiteNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class SiteNotificationsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_the_bell_shows_the_unread_count_and_the_page_lists_notifications(): void
    {
        $pupil = $this->student();
        $notifier = app(SiteNotifier::class);
        $notifier->send($pupil, 'Première', 'Détail <b>un</b>', 'warning');
        $notifier->send($pupil, 'Deuxième');
        $notifier->send($this->student(), 'Pour un autre');

        $this->actingAs($pupil)->get(route('reservations.index'))->assertOk()->assertSee('2 non lues');
        $this->get(route('notifications.index'))->assertOk()->assertSee('Première')->assertSee('Deuxième')
            ->assertDontSee('Pour un autre')->assertSee('Détail &lt;b&gt;un&lt;/b&gt;', false);
    }

    public function test_reading_one_or_all_notifications(): void
    {
        $pupil = $this->student();
        $first = app(SiteNotifier::class)->send($pupil, 'A');
        app(SiteNotifier::class)->send($pupil, 'B');

        $this->actingAs($pupil)->post(route('notifications.read', $first->id))->assertRedirect();
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(1, $pupil->unreadNotificationCount());

        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $pupil->unreadNotificationCount());
        $this->get(route('reservations.index'))->assertDontSee('non lues');
    }

    public function test_nobody_reads_or_deletes_another_accounts_notifications(): void
    {
        $owner = $this->student();
        $notification = app(SiteNotifier::class)->send($owner, 'Privée');

        $this->actingAs($this->student())->post(route('notifications.read', $notification->id))->assertNotFound();
        $this->delete(route('notifications.destroy', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($owner)->delete(route('notifications.destroy', $notification->id))->assertRedirect();
        $this->assertNull(UserNotification::find($notification->id));
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }

    public function test_notifications_sent_by_an_administrator_reach_the_pupils_without_any_email_step(): void
    {
        $pupil = $this->student();

        $this->actingAs($this->admin())->post(route('admin.notifications.store'), ['title' => 'Fermeture', 'content' => 'Le CDI ferme à 16h.', 'level' => 'info', 'target' => 'users', 'user_ids' => [$pupil->id]])
            ->assertSessionHasNoErrors();

        $this->actingAs($pupil)->get(route('notifications.index'))->assertSee('Fermeture')->assertSee('Le CDI ferme');
    }
}
