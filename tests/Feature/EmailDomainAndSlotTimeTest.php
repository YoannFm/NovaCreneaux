<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailDomainAndSlotTimeTest extends TestCase
{
    use RefreshDatabase;

    private function allowDomain(string $domain): void
    {
        Setting::updateOrCreate(['key' => 'allowed_email_domain'], ['value' => $domain]);
        cache()->forget('setting_allowed_email_domain');
    }

    public function test_changing_email_to_another_domain_is_refused_when_a_domain_is_enforced(): void
    {
        $this->allowDomain('e.rascol.net');
        $user = User::factory()->create(['email' => 'eleve@e.rascol.net']);

        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => 'eleve@gmail.com'])
            ->assertSessionHasErrors('email');
        $this->assertSame('eleve@e.rascol.net', $user->fresh()->email);
    }

    public function test_changing_email_inside_the_domain_is_accepted(): void
    {
        $this->allowDomain('e.rascol.net');
        $user = User::factory()->create(['email' => 'eleve@e.rascol.net']);

        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => 'autre@e.rascol.net'])
            ->assertSessionHasNoErrors();
    }

    public function test_existing_account_outside_the_domain_can_still_update_its_name(): void
    {
        $this->allowDomain('e.rascol.net');
        $user = User::factory()->create(['email' => 'prof@rascol.net']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Nouveau Nom', 'email' => 'prof@rascol.net'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_slot_that_already_started_today_cannot_be_booked(): void
    {
        $resource = Resource::create(['name' => 'CDI', 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        $slot = Slot::create([
            'resource_id' => $resource->id, 'name' => 'Matin',
            'start_time' => '00:00', 'end_time' => '00:01', 'max_capacity' => 5,
        ]);
        $user = User::factory()->create(['role_id' => Role::defaultUser()->id]);

        $result = app(ReservationService::class)->create($user, $slot->id, now()->toDateString());

        $this->assertFalse($result['ok']);
        $this->assertSame('Ce créneau a déjà commencé.', $result['error']);
    }
}
