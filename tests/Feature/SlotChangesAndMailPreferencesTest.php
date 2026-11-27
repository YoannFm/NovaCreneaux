<?php

namespace Tests\Feature;

use App\Mail\ReservationCancelled;
use App\Mail\ReservationConfirmed;
use App\Mail\ReservationRescheduled;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\UserNotification;
use App\Services\ReservationService;
use App\Support\MailPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Changement d'horaire et capacite d'un creneau reserve, choix des e-mails dans le profil. */
class SlotChangesAndMailPreferencesTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-25 10:00:00');
        $this->slot = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []])
            ->slots()->create(['name' => 'Midi', 'start_time' => '12:00', 'end_time' => '13:00', 'max_capacity' => 3, 'is_active' => true]);
    }

    private function book(string $date, array $attributes = []): Reservation
    {
        return Reservation::create(['user_id' => $this->student($attributes)->id, 'slot_id' => $this->slot->id, 'reservation_date' => $date]);
    }

    private function updateSlot(array $changes)
    {
        return $this->actingAs($this->admin())->put(route('admin.slots.update', [$this->slot->resource_id, $this->slot]),
            $changes + ['name' => 'Midi', 'start_time' => '12:00', 'end_time' => '13:00', 'max_capacity' => 3, 'is_active' => '1']);
    }

    // --- Changement d'horaire

    public function test_a_new_time_warns_only_the_people_still_to_come(): void
    {
        Mail::fake();
        $past = $this->book('2026-11-24');
        $tomorrow = $this->book('2026-11-26');

        $this->updateSlot(['start_time' => '12:30', 'end_time' => '13:30'])
            ->assertSessionHas('success', "Créneau mis à jour. 1 personne(s) ayant réservé ont été prévenues du nouvel horaire.");

        Mail::assertSent(ReservationRescheduled::class, 1);
        Mail::assertSent(ReservationRescheduled::class, fn ($mail) => $mail->hasTo($tomorrow->user->email) && $mail->previous === '12:00 - 13:00');
        $this->assertTrue(UserNotification::where('user_id', $tomorrow->user_id)->where('title', 'Horaire modifié')->exists());
        $this->assertFalse(UserNotification::where('user_id', $past->user_id)->exists());
        $this->assertNotNull($tomorrow->fresh(), 'la reservation est gardee');

        $html = (new ReservationRescheduled($tomorrow->fresh()->load('slot.resource', 'user'), '12:00 - 13:00'))->render();
        $this->assertStringContainsString('Nouvel horaire :</strong> 12:30 - 13:30', $html);
        $this->assertStringContainsString('Ancien horaire : 12:00 - 13:00', $html);
    }

    public function test_changing_something_else_warns_nobody(): void
    {
        Mail::fake();
        $this->book('2026-11-26');

        $this->updateSlot(['name' => 'Pause de midi'])->assertSessionHas('success', 'Créneau mis à jour.');

        Mail::assertNothingSent();
    }

    public function test_times_read_back_with_seconds_like_on_mysql_are_not_a_change(): void
    {
        Mail::fake();
        $this->book('2026-11-26');
        // MySQL renvoie une colonne TIME en HH:MM:SS, le formulaire envoie HH:MM
        Slot::whereKey($this->slot->id)->update(['start_time' => '12:00:00', 'end_time' => '13:00:00']);

        $this->updateSlot(['max_capacity' => 4])->assertSessionHas('success', 'Créneau mis à jour.');

        Mail::assertNothingSent();
        $this->assertSame(0, UserNotification::count());
        $this->assertArrayNotHasKey('start_time', ActivityLog::where('action', 'Créneau modifié')->latest('id')->value('changes') ?? []);
    }

    // --- Capacite

    public function test_a_capacity_below_the_bookings_is_reported_without_cancelling(): void
    {
        foreach (range(1, 3) as $i) {
            $this->book('2026-11-26');
        }
        $this->book('2026-11-27');

        $this->updateSlot(['max_capacity' => 2])
            ->assertSessionHas('warning', "La capacité (2 places) est inférieure aux réservations déjà faites : jeudi 26 novembre (3 réservations). Aucune réservation n'a été annulée : retirez-en depuis le planning si besoin.");

        $this->assertSame(4, Reservation::count());
        $this->updateSlot(['max_capacity' => 3])->assertSessionMissing('warning');
    }

    // --- Choix des e-mails

    public function test_everyone_receives_every_optional_mail_by_default(): void
    {
        $user = $this->student();

        foreach (array_keys(MailPreferences::LABELS) as $kind) {
            $this->assertTrue($user->wantsMail($kind), $kind);
        }
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Confirmation de mes réservations')->assertSee('id="mail-slot_changed"', false);
    }

    public function test_an_unchecked_mail_is_no_longer_sent_but_the_bell_still_is(): void
    {
        Mail::fake();
        $student = $this->student();
        $this->actingAs($student)->patch(route('profile.mails'), ['mails' => [MailPreferences::CANCELLED_BY_ADMIN]])->assertSessionHasNoErrors();

        $service = app(ReservationService::class);
        $result = $service->create($student->fresh(), $this->slot->id, '2026-11-26');
        Mail::assertNotSent(ReservationConfirmed::class);

        $service->cancelByAdmin($result['reservation'], 'Fermeture');
        Mail::assertSent(ReservationCancelled::class, 1);
        $this->assertTrue(UserNotification::where('user_id', $student->id)->exists());

        $this->patch(route('profile.mails'), ['mails' => []]);
        $this->assertSame(array_keys(MailPreferences::LABELS), $student->fresh()->mail_opt_out);
        $this->patch(route('profile.mails'), ['mails' => ['inconnu']])->assertSessionHasErrors('mails.0');
    }
}
