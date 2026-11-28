<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReservationRulesTest extends TestCase
{
    use RefreshDatabase;

    private function slot(int $capacity = 5, string $name = 'Recre'): Slot
    {
        $resource = Resource::firstOrCreate(
            ['name' => 'CDI'],
            ['open_time' => '07:00', 'close_time' => '23:59', 'closed_days' => []]
        );

        return Slot::create([
            'resource_id' => $resource->id, 'name' => $name,
            'start_time' => '23:00', 'end_time' => '23:30', 'max_capacity' => $capacity,
        ]);
    }

    private function student(): User
    {
        return User::factory()->create(['role_id' => Role::defaultUser()->id]);
    }

    private function tomorrow(): string
    {
        return now()->addDay()->toDateString();
    }

    public function test_a_full_slot_refuses_further_reservations(): void
    {
        $slot = $this->slot(capacity: 1);
        $service = app(ReservationService::class);

        $this->assertTrue($service->create($this->student(), $slot->id, $this->tomorrow())['ok']);
        $second = $service->create($this->student(), $slot->id, $this->tomorrow());

        $this->assertFalse($second['ok']);
        $this->assertSame(1, Reservation::count());
    }

    public function test_a_user_cannot_book_the_same_slot_twice(): void
    {
        $slot = $this->slot();
        $user = $this->student();
        $service = app(ReservationService::class);

        $this->assertTrue($service->create($user, $slot->id, $this->tomorrow())['ok']);
        $this->assertFalse($service->create($user, $slot->id, $this->tomorrow())['ok']);
    }

    public function test_the_daily_limit_of_the_role_is_enforced(): void
    {
        Role::defaultUser()->update(['max_reservations_day' => 1]);
        $user = $this->student();
        $first = $this->slot(name: 'A');
        $second = $this->slot(name: 'B');
        $service = app(ReservationService::class);

        $this->assertTrue($service->create($user, $first->id, $this->tomorrow())['ok']);
        $this->assertFalse($service->create($user, $second->id, $this->tomorrow())['ok']);
    }

    public function test_registered_users_get_the_default_role(): void
    {
        Setting::updateOrCreate(['key' => 'registration_open'], ['value' => '1']);

        $this->post('/register', [
            'name' => 'Eleve Test', 'email' => 'eleve@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ]);

        $this->assertSame(Role::defaultUser()->id, User::where('email', 'eleve@example.com')->value('role_id'));
    }

    public function test_csv_imported_users_get_the_default_role(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_admin' => true]);
        $csv = UploadedFile::fake()->createWithContent('eleves.csv', "Nom,Prenom,Classe,Email\nDupont,Marie,2nde A,marie@example.com\n");

        $this->actingAs($admin)->post(route('admin.users.import.store'), ['csv' => $csv]);

        $this->assertSame(Role::defaultUser()->id, User::where('email', 'marie@example.com')->value('role_id'));
    }
}
