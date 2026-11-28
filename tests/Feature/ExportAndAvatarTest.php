<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use App\Support\CsvCell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportAndAvatarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
    }

    public function test_formula_prefixes_are_neutralised(): void
    {
        $this->assertSame("'=1+1", CsvCell::safe('=1+1'));
        $this->assertSame("'@SUM(A1)", CsvCell::safe('@SUM(A1)'));
        $this->assertSame("'-2", CsvCell::safe('-2'));
        $this->assertSame('Marie Dupont', CsvCell::safe('Marie Dupont'));
        $this->assertSame('', CsvCell::safe(null));
    }

    public function test_the_reservation_export_does_not_carry_formulas(): void
    {
        $resource = Resource::create(['name' => 'CDI', 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        $slot = Slot::create(['resource_id' => $resource->id, 'name' => 'Recre', 'start_time' => '10:00', 'end_time' => '10:15', 'max_capacity' => 5]);
        $student = User::factory()->create(['name' => '=HYPERLINK("http://evil.example")', 'role_id' => Role::defaultUser()->id]);
        Reservation::create(['user_id' => $student->id, 'slot_id' => $slot->id, 'reservation_date' => now()->toDateString()]);

        $csv = $this->actingAs($this->admin())->get(route('admin.reservations.index', ['export' => 'csv']))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
        $this->assertStringNotContainsString("\n=HYPERLINK", $csv);
    }

    public function test_initials_use_the_first_letters_of_the_name(): void
    {
        $this->assertSame('MD', (new User(['name' => 'marie dupont']))->initials());
        $this->assertSame('A', (new User(['name' => 'Admin']))->initials());
        $this->assertSame('ÉL', (new User(['name' => 'élodie léon martin']))->initials());
        $this->assertSame('?', (new User(['name' => '  ']))->initials());
    }

    public function test_the_admin_layout_does_not_call_an_external_avatar_service(): void
    {
        $this->actingAs($this->admin(['name' => 'Yoann Test']))->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee('gravatar', false)->assertSee('YT');
    }
}
