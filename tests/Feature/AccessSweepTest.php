<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Chaque route de l'administration, appelee par un eleve et par un membre du personnel qui n'a que la permission
 * des pages : aucune ne repond (sauf les pages et le tableau de bord pour ce dernier). Un eleve ne touche pas aux
 * reservations ni aux notifications d'un autre. Une nouvelle route d'administration est couverte d'office.
 */
class AccessSweepTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_admin_routes_and_other_accounts_data_stay_closed(): void
    {
        Mail::fake();
        \Illuminate\Support\Facades\Http::fake();
        $student = $this->student();
        $other = $this->student();
        $staffNone = $this->staff([\App\Support\Permission::PAGES], 'pages-seulement');
        $res = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        $slot = $res->slots()->create(['name' => 'M', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 5, 'is_active' => true]);
        $resa = Reservation::create(['user_id' => $other->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);
        $notif = UserNotification::create(['user_id' => $other->id, 'title' => 'x', 'type' => 'info']);
        $params = ['resource' => $res->id, 'slot' => $slot->id, 'user' => $other->id, 'reservation' => $resa->id, 'role' => 1, 'id' => $notif->id, 'notification' => $notif->id];
        $bad = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'admin')) continue;
            $path = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $params[$m[1]] ?? 1, $uri);
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') continue;
                foreach (['eleve' => $student, 'personnel pages' => $staffNone] as $label => $who) {
                    $this->flushSession(); auth()->logout(); $this->actingAs($who);
                    $r = $this->call($method, '/' . $path);
                    $code = $r->getStatusCode();
                    $allowedForPagesStaff = str_starts_with($uri, 'admin/pages') || $uri === 'admin' || $uri === 'admin/dashboard';
                    if ($code < 300 && !($label === 'personnel pages' && $allowedForPagesStaff)) $bad[] = "$label $method /$path -> $code";
                }
            }
        }
        // Donnees d'un autre eleve
        $this->flushSession(); auth()->logout(); $this->actingAs($student);
        foreach ([['DELETE', '/reservations/' . $resa->id], ['POST', "/notifications/{$notif->id}/read"], ['DELETE', "/notifications/{$notif->id}"]] as [$m, $u]) {
            $c = $this->call($m, $u)->getStatusCode();
            if ($c < 300 || ($c >= 300 && $c < 400 && Reservation::find($resa->id) === null)) $bad[] = "IDOR $m $u -> $c";
        }
        $this->assertNotNull(Reservation::find($resa->id));
        $this->assertNull(UserNotification::find($notif->id)->read_at);
        $this->assertNotNull(UserNotification::find($notif->id));
        $this->assertSame([], $bad);
    }
}
