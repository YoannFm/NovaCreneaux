<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Sanction;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** Le bouton de chaque creneau dit ce que le serveur repondrait : il n'est actif que si la reservation passerait. */
class ReservationButtonStateTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const TODAY = '2026-11-25';
    private const TOMORROW = '2026-11-26';

    private Slot $morning;
    private Slot $afternoon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::TODAY . ' 10:00:00');
        $cdi = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        $this->morning = $cdi->slots()->create(['name' => 'M1', 'start_time' => '08:00', 'end_time' => '12:00', 'max_capacity' => 24, 'is_active' => true]);
        $this->afternoon = $cdi->slots()->create(['name' => 'S1', 'start_time' => '14:00', 'end_time' => '16:00', 'max_capacity' => 24, 'is_active' => true]);
        Role::defaultUser()->update(['max_reservations_day' => 1, 'max_reservations_week' => -1, 'advance_days' => 3]);
    }

    /** Libelle du bouton d'un creneau pour une date. */
    private function button(string $html, Slot $slot, string $date): string
    {
        $pattern = '/name="slot_id" value="' . $slot->id . '">\s*<input type="hidden" name="reservation_date" value="' . $date . '">\s*<button([^>]*)>\s*<i[^>]*><\/i>\s*([^<]+?)\s*<\/button>/u';
        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $match);

        return trim($match[2]) . (str_contains($match[1], 'disabled') ? ' (inactif)' : '');
    }

    public function test_each_button_reflects_the_rule_the_server_would_apply(): void
    {
        $student = $this->student();
        Reservation::create(['user_id' => $student->id, 'slot_id' => $this->afternoon->id, 'reservation_date' => self::TOMORROW]);

        $html = $this->actingAs($student)->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertSame('Commencé (inactif)', $this->button($html, $this->morning, self::TODAY));
        $this->assertSame('Réserver', $this->button($html, $this->afternoon, self::TODAY));
        $this->assertSame('Réservé (inactif)', $this->button($html, $this->afternoon, self::TOMORROW));
        $this->assertSame('Limite atteinte (inactif)', $this->button($html, $this->morning, self::TOMORROW));
    }

    public function test_a_suspended_account_sees_why_and_cannot_click(): void
    {
        $student = $this->student();
        Sanction::create(['user_id' => $student->id, 'admin_id' => $this->admin()->id, 'type' => 'ban', 'reason' => 'Retards répétés', 'banned_until' => null]);

        $html = $this->actingAs($student)->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertSame('Suspendu (inactif)', $this->button($html, $this->afternoon, self::TODAY));
        $this->assertStringContainsString('Motif : Retards répétés', $html);
    }

    public function test_the_page_does_not_query_once_per_slot(): void
    {
        $student = $this->student();
        $this->actingAs($student)->get(route('reservations.index'))->assertOk();
        foreach (range(1, 10) as $i) {
            $this->morning->resource->slots()->create(['name' => "Autre {$i}", 'start_time' => '15:00', 'end_time' => '16:00', 'max_capacity' => 5, 'is_active' => true]);
        }

        DB::enableQueryLog();
        $this->get(route('reservations.index'))->assertOk();
        $withMoreSlots = count(DB::getQueryLog());
        $this->morning->resource->slots()->where('name', 'like', 'Autre%')->delete();
        DB::flushQueryLog();
        $this->get(route('reservations.index'))->assertOk();

        $this->assertSame(count(DB::getQueryLog()), $withMoreSlots);
    }
}
