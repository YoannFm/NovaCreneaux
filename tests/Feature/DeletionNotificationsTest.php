<?php

namespace Tests\Feature;

use App\Mail\AccountDeleted;
use App\Mail\ReservationCancelled;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class DeletionNotificationsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const PASSWORD = 'MotDePasse-Test-1';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function slot(): Slot
    {
        $resource = Resource::create(['name' => 'Salle B', 'is_active' => true, 'open_time' => '08:00', 'close_time' => '18:00']);

        return $resource->slots()->create(['name' => 'Matin', 'start_time' => '08:00', 'end_time' => '10:00', 'max_capacity' => 5, 'is_active' => true]);
    }

    private function book(User $user, Slot $slot, string $date): Reservation
    {
        return Reservation::create(['user_id' => $user->id, 'slot_id' => $slot->id, 'reservation_date' => $date]);
    }

    public function test_an_admin_cancellation_warns_the_pupil_on_the_site_and_by_email_with_the_reason(): void
    {
        $pupil = $this->student();
        $reservation = $this->book($pupil, $this->slot(), now()->addDays(2)->toDateString());

        $this->actingAs($this->admin())->delete(route('admin.reservations.destroy', $reservation), ['reason' => 'Salle fermée'])->assertSessionHas('success');

        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
        Mail::assertSent(ReservationCancelled::class, fn ($mail) => $mail->hasTo($pupil->email) && $mail->byAdmin && $mail->reason === 'Salle fermée');
        $notification = UserNotification::where('user_id', $pupil->id)->sole();
        $this->assertSame('Réservation annulée par un administrateur', $notification->title);
        $this->assertStringContainsString('Salle B, Matin', $notification->message);
        $this->assertStringContainsString('Motif : Salle fermée', $notification->message);
        $this->assertNull($notification->read_at);
    }

    public function test_the_reason_is_optional_and_limited(): void
    {
        $pupil = $this->student();
        $reservation = $this->book($pupil, $this->slot(), now()->addDay()->toDateString());

        $this->actingAs($this->admin())->delete(route('admin.reservations.destroy', $reservation), ['reason' => str_repeat('x', 301)])->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);

        $this->delete(route('admin.reservations.destroy', $reservation))->assertSessionHas('success');
        $this->assertStringNotContainsString('Motif', UserNotification::where('user_id', $pupil->id)->sole()->message);
    }

    public function test_a_pupil_cancelling_their_own_reservation_gets_the_email_but_no_site_notification(): void
    {
        $pupil = $this->student();
        $reservation = $this->book($pupil, $this->slot(), now()->addDays(3)->toDateString());

        $this->actingAs($pupil)->delete(route('reservations.destroy', $reservation->id));

        Mail::assertSent(ReservationCancelled::class, fn ($mail) => !$mail->byAdmin);
        $this->assertSame(0, UserNotification::count());
    }

    public function test_deleting_a_slot_cancels_upcoming_reservations_only_and_warns_the_pupils(): void
    {
        $slot = $this->slot();
        $future = $this->student();
        $past = $this->student();
        $this->book($future, $slot, now()->addDays(4)->toDateString());
        $this->book($past, $slot, now()->subDays(4)->toDateString());

        $this->actingAs($this->admin())->delete(route('admin.slots.destroy', [$slot->resource, $slot]))
            ->assertSessionHas('success', fn ($message) => str_contains($message, '1 réservation(s) à venir annulée(s)'));

        $this->assertSame(1, UserNotification::where('user_id', $future->id)->count());
        $this->assertSame(0, UserNotification::where('user_id', $past->id)->count());
        $this->assertStringContainsString("n'existe plus", UserNotification::where('user_id', $future->id)->value('message'));
    }

    public function test_deleting_a_resource_cancels_the_upcoming_reservations_of_all_its_slots(): void
    {
        $slot = $this->slot();
        $other = $slot->resource->slots()->create(['name' => 'Après-midi', 'start_time' => '14:00', 'end_time' => '16:00', 'max_capacity' => 5, 'is_active' => true]);
        $one = $this->student();
        $two = $this->student();
        $this->book($one, $slot, now()->addDay()->toDateString());
        $this->book($two, $other, now()->addDays(2)->toDateString());

        $this->actingAs($this->admin())->delete(route('admin.resources.destroy', $slot->resource))->assertSessionHas('success');

        $this->assertSame(1, UserNotification::where('user_id', $one->id)->count());
        $this->assertSame(1, UserNotification::where('user_id', $two->id)->count());
        $this->assertStringContainsString('n\'est plus disponible', UserNotification::where('user_id', $one->id)->value('message'));
    }

    public function test_an_admin_deleting_an_account_warns_its_owner_unless_disabled(): void
    {
        $admin = $this->admin();
        $one = $this->student();
        $two = $this->student();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $one));
        Mail::assertSent(AccountDeleted::class, fn ($mail) => $mail->hasTo($one->email) && $mail->byAdmin && $mail->name === $one->name);
        $this->assertDatabaseMissing('users', ['id' => $one->id]);

        app(SettingService::class)->set('notify_account_deletion', '0');
        $this->delete(route('admin.users.destroy', $two));
        Mail::assertSentCount(1);
        $this->assertDatabaseMissing('users', ['id' => $two->id]);
    }

    public function test_bulk_deletion_warns_each_account_and_never_the_actor(): void
    {
        $admin = $this->admin();
        $pupils = [$this->student(), $this->student(), $this->student()];

        $this->actingAs($admin)->post(route('admin.users.bulk'), ['action' => 'delete', 'ids' => array_merge(array_map(fn ($u) => $u->id, $pupils), [$admin->id])]);

        Mail::assertSent(AccountDeleted::class, 3);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_a_user_deleting_their_own_account_is_always_warned(): void
    {
        app(SettingService::class)->set('notify_account_deletion', '0');
        $pupil = User::factory()->create(['role_id' => \App\Models\Role::defaultUser()->id, 'password' => bcrypt(self::PASSWORD)]);

        $this->actingAs($pupil)->delete(route('profile.destroy'), ['password' => self::PASSWORD]);

        Mail::assertSent(AccountDeleted::class, fn ($mail) => $mail->hasTo($pupil->email) && !$mail->byAdmin);
        $this->assertDatabaseMissing('users', ['id' => $pupil->id]);
    }

    public function test_a_failing_mail_never_blocks_the_deletion(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP indisponible'));
        $pupil = $this->student();

        $this->actingAs($this->admin())->delete(route('admin.users.destroy', $pupil))->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $pupil->id]);
    }

    public function test_the_mail_page_has_the_setting_and_saves_it(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.mail'))->assertOk()->assertSee('notify_account_deletion');
        $this->post(route('admin.settings.mail.update'), ['mail_driver' => 'disabled', 'notify_account_deletion' => '0'])->assertSessionHasNoErrors();

        $this->assertSame('0', setting('notify_account_deletion'));
    }
}
