<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fenetre de reservation (ressource) et limite "a l'avance" (role) : chaque jour a l'heure d'ouverture,
 * une journee de plus s'ouvre. Cas reel : CDI ouvert a 12:00 pour le lendemain, ferme a 11:30 le jour meme.
 */
class ReservationWindowTest extends TestCase
{
    use RefreshDatabase;

    private const WEDNESDAY = '2026-11-25';

    private Slot $slot;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Role::defaultUser()->update(['max_reservations_day' => 1, 'max_reservations_week' => 4, 'cancel_deadline_minutes' => 30, 'advance_days' => 1]);
        $resource = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '12:00', 'close_time' => '11:30', 'closed_days' => []]);
        $this->slot = Slot::create(['resource_id' => $resource->id, 'name' => 'M4', 'start_time' => '11:00', 'end_time' => '12:00', 'max_capacity' => 20, 'is_active' => true]);
        $this->student = User::factory()->create(['role_id' => Role::defaultUser()->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse(self::WEDNESDAY . ' ' . $time));
    }

    private function check(string $date): ?array
    {
        return app(ReservationService::class)->checkBookable($this->student->fresh(), $this->slot, $date);
    }

    public function test_tomorrow_opens_at_noon_and_today_closes_at_half_past_eleven(): void
    {
        $this->at('09:00');
        $this->assertNull($this->check('2026-11-25'), "aujourd'hui avant 11:30 et avant le debut du creneau");
        $this->assertSame("Ouverture des réservations aujourd'hui à 12:00.", $this->check('2026-11-26')['error']);

        $this->at('12:00');
        $this->assertNull($this->check('2026-11-26'), 'demain est ouvert depuis midi');
        $this->assertSame('resource_closed_today', $this->check('2026-11-25')['code']);
        $this->assertSame("Vous ne pouvez pas réserver plus de 1 jour(s) à l'avance.", $this->check('2026-11-27')['error']);
    }

    public function test_a_longer_advance_opens_its_last_day_at_the_same_hour(): void
    {
        Role::defaultUser()->update(['advance_days' => 30]);

        $this->at('11:59');
        $this->assertNull($this->check(Carbon::parse(self::WEDNESDAY)->addDays(29)->toDateString()));
        $this->assertSame('advance_limit', $this->check(Carbon::parse(self::WEDNESDAY)->addDays(30)->toDateString())['code']);

        $this->at('12:00');
        $this->assertNull($this->check(Carbon::parse(self::WEDNESDAY)->addDays(30)->toDateString()));
    }

    public function test_the_booking_page_shows_when_tomorrow_opens(): void
    {
        $this->at('09:00');

        $this->actingAs($this->student)->get(route('reservations.index'))->assertOk()
            ->assertSee("Ouverture des réservations aujourd&#039;hui à 12:00.", false)
            ->assertSee("Nouveau jour à 12:00 · le jour même jusqu'à 11:30", false);
    }

    public function test_my_reservations_offers_cancelling_until_the_deadline_only(): void
    {
        $reservation = Reservation::create(['user_id' => $this->student->id, 'slot_id' => $this->slot->id, 'reservation_date' => '2026-11-26']);

        $this->at('12:00');
        $this->actingAs($this->student)->get(route('reservations.my'))->assertOk()
            ->assertSee('Annuler')->assertSee('jeu. 26 nov. à 10:30');

        // Jeudi 10:40 : le creneau commence a 11:00, le delai de 30 minutes est passe
        Carbon::setTestNow(Carbon::parse('2026-11-26 10:40'));
        $this->get(route('reservations.my'))->assertOk()
            ->assertDontSee('confirm(\'Annuler cette réservation ?\')', false)
            ->assertSee('Annulation impossible depuis 10:30');
        $this->assertSame('cancel_deadline', app(ReservationService::class)->cancel($this->student, $reservation->id)['code']);

        Carbon::setTestNow(Carbon::parse('2026-11-26 10:29'));
        $this->assertTrue(app(ReservationService::class)->cancel($this->student, $reservation->id)['ok']);
    }

    public function test_an_account_without_role_sees_as_many_days_as_it_may_book(): void
    {
        // Avant : la page montrait 1 jour, alors que la reservation en acceptait 30
        $user = User::factory()->create(['role_id' => null]);
        $this->at('12:00');

        $html = $this->actingAs($user)->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertSame(User::DEFAULT_ADVANCE_DAYS, $user->advanceDays());
        $this->assertSame(User::DEFAULT_ADVANCE_DAYS + 1, substr_count($html, 'class="date-panel'));
    }
}
