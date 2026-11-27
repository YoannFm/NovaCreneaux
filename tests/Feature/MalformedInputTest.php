<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Chaque route du site, appelee en invite, en eleve et en administrateur avec des champs et des parametres d'adresse
 * envoyes sous forme de tableau (requete modifiee a la main) : jamais d'erreur 500. Une valeur lue sans validation
 * (`(string) $request->input(...)`), une regle maison qui suppose du texte ou une validation sans `bail` avant la
 * verification du mot de passe faisaient planter la page. Une nouvelle route est couverte d'office.
 */
class MalformedInputTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_no_route_fails_with_values_sent_as_arrays(): void
    {
        Mail::fake();
        \Illuminate\Support\Facades\Http::fake();
        $admin = $this->admin();
        $student = $this->student();
        $res = Resource::create(['name' => 'CDI', 'is_active' => true, 'open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);
        $slot = $res->slots()->create(['name' => 'M', 'start_time' => '08:00', 'end_time' => '09:00', 'max_capacity' => 5, 'is_active' => true]);
        $resa = Reservation::create(['user_id' => $student->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);
        $page = Page::create(['title' => 'P', 'slug' => 'p', 'content' => 'x', 'is_enabled' => true]);
        $params = ['resource' => $res->id, 'slot' => $slot->id, 'user' => $student->id, 'reservation' => $resa->id, 'role' => Role::defaultUser()->id,
            'page' => $page->id, 'slug' => 'p', 'id' => 1, 'nav' => 1, 'sanction' => 1, 'notification' => 1, 'token' => 1, 'webhook' => 1, 'name' => 'x', 'template' => 'cdi',
            'plugin' => 'x', 'credentialId' => 'x', 'theme' => 'x', 'delivery' => 1, 'log' => 1, 'hash' => 'x'];
        $keys = ['q', 'search', 'date', 'from', 'to', 'role', 'user', 'action', 'resource_id', 'layout', 'export', 'type', 'status', 'code', 'method', 'password',
            'current_password', 'email', 'name', 'title', 'slug', 'content', 'label', 'url', 'reason', 'message', 'value', 'contact', 'assertion', 'ticket', 'state',
            'ids', 'mails', 'roles', 'permissions', 'slot_id', 'reservation_date', 'user_id', 'theme_mode', 'accent_color', 'classe', 'events', 'format', 'preset',
            'fields', 'body', 'headers', 'severity', 'duration', 'banned_until', 'test_email', 'app_name', 'site_url', 'timezone', 'backup_time', 'backup_frequency',
            'max_capacity', 'start_time', 'end_time', 'open_time', 'close_time', 'description', 'icon', 'color', 'abilities', 'expires', 'trusted_proxies', 'sms_driver',
            'mail_driver', 'sso_driver', 'oidc_issuer', 'cas_url', 'home_message', 'registration_terms', 'allowed_email_domain', 'maintenance_message', 'target', 'position'];
        $bad = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            // Assistant d'installation (ferme), deconnexion, mises a jour (telechargements) : hors du perimetre
            if (str_starts_with($uri, '_') || str_starts_with($uri, 'install') || str_contains($uri, 'logout') || str_contains($uri, 'update/') || str_starts_with($uri, 'sanctum') || str_starts_with($uri, 'storage')) continue;
            $path = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $params[$m[1]] ?? 1, $uri);
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') continue;
                foreach ([$admin, $student, null] as $who) {
                    $this->flushSession();
                    auth()->logout();
                    if ($who) $this->actingAs($who);
                    $data = array_fill_keys($keys, ['x']);
                    $response = $method === 'GET'
                        ? $this->get('/' . ltrim($path, '/') . '?' . http_build_query($data))
                        : $this->call($method, '/' . ltrim($path, '/'), $data);
                    if ($response->getStatusCode() >= 500) {
                        $e = $response->exception ?? null;
                        $bad[] = "$method /$path (" . ($who?->email ?? 'invite') . ") " . $response->status() . ' ' . ($e ? get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 150) . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : '');
                    }
                }
            }
        }
        $this->assertSame([], array_values(array_unique($bad)));
    }
}
