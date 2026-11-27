<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Slot;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Les compteurs de l'accueil suivent les regles de la page de reservation, et le personnel ne reserve pas pour un compte desactive. */
class HomeCountersTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private Slot $morning;
    private Slot $noon;

    protected function setUp(): void
    {
        parent::setUp();
        $cdi = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '17:00', 'close_time' => '11:30', 'closed_days' => []]);
        $this->morning = $cdi->slots()->create(['name' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 24, 'is_active' => true]);
        $this->noon = $cdi->slots()->create(['name' => 'Midi', 'start_time' => '11:55', 'end_time' => '12:50', 'max_capacity' => 24, 'is_active' => true]);
    }

    private function stat(string $html, string $label): int
    {
        $this->assertMatchesRegularExpression('#<div class="stat-value">(\d+)</div>\s*<div class="stat-label">' . preg_quote($label, '#') . '#', $html);
        preg_match('#<div class="stat-value">(\d+)</div>\s*<div class="stat-label">' . preg_quote($label, '#') . '#', $html, $match);

        return (int) $match[1];
    }

    public function test_slots_available_today_follow_the_booking_rules(): void
    {
        $student = $this->student();

        $this->travelTo('2026-11-25 07:00:00');
        $this->assertSame(2, $this->stat($this->actingAs($student)->get('/')->getContent(), "Créneaux disponibles aujourd'hui"));

        $this->travelTo('2026-11-25 10:00:00');
        $this->assertSame(1, $this->stat($this->get('/')->getContent(), "Créneaux disponibles aujourd'hui"), 'M1 a commence');

        $this->assertSame(1, $this->stat($this->get('/')->getContent(), "Ressources disponibles aujourd'hui"));

        $this->travelTo('2026-11-25 15:00:00');
        $html = $this->get('/')->getContent();
        $this->assertSame(0, $this->stat($html, "Créneaux disponibles aujourd'hui"), 'reservation du jour fermee');
        $this->assertSame(0, $this->stat($html, "Ressources disponibles aujourd'hui"), 'plus rien a reserver au CDI');
        $this->assertStringNotContainsString("Où réserver aujourd'hui", $html);
    }

    public function test_a_finished_session_is_no_longer_upcoming(): void
    {
        $student = $this->student();
        $this->travelTo('2026-11-25 15:00:00');
        Reservation::create(['user_id' => $student->id, 'slot_id' => $this->noon->id, 'reservation_date' => '2026-11-25']);
        Reservation::create(['user_id' => $student->id, 'slot_id' => $this->morning->id, 'reservation_date' => '2026-11-27']);

        $html = $this->actingAs($student)->get('/')->getContent();

        $this->assertSame(1, $this->stat($html, 'Mes réservations à venir'));
        $this->assertSame([$this->morning->id], $student->upcomingReservations()->pluck('slot_id')->all());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Midi');
    }

    public function test_staff_cannot_book_for_a_disabled_account(): void
    {
        $this->travelTo('2026-11-25 07:00:00');
        $disabled = $this->student(['is_active' => false]);

        $result = app(ReservationService::class)->createByStaff($disabled, $this->noon->id, '2026-11-26');

        $this->assertSame('account_disabled', $result['code'] ?? null);
        $this->actingAs($this->admin())->post(route('admin.reservations.store'), ['user_id' => $disabled->id, 'slot_id' => $this->noon->id, 'date' => '2026-11-26'])
            ->assertSessionHasErrors();
        $this->assertSame(0, Reservation::count());
    }

    public function test_my_reservations_shows_a_session_in_progress(): void
    {
        $student = $this->student();
        $this->travelTo('2026-11-25 12:10:00');
        Reservation::create(['user_id' => $student->id, 'slot_id' => $this->noon->id, 'reservation_date' => '2026-11-25']);

        $this->actingAs($student)->get(route('reservations.my'))->assertOk()->assertSee('En cours')->assertDontSee('Passée');
    }
}
