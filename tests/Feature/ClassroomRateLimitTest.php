<?php

namespace Tests\Feature;

use App\Services\SettingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * Une classe entiere arrive avec l'adresse IP du lycee : les pages ouvertes aux visiteurs limitent chaque personne
 * (session ou adresse saisie), pas l'adresse IP, qui n'a qu'un plafond large contre les abus.
 */
class ClassroomRateLimitTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const CLASS_SIZE = 35;

    /** Un autre eleve, sur un autre poste de la meme salle : la meme adresse IP, une session neuve, personne de connecte. */
    private function nextStudent(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->driver()->flush();
        $this->app['session']->driver()->setId(Str::random(40));
    }

    public function test_each_guest_form_limits_one_person_and_keeps_a_wide_cap_per_ip(): void
    {
        $request = Request::create('/', 'POST', ['email' => 'Lea@Exemple.fr'], server: ['REMOTE_ADDR' => '203.0.113.5']);

        foreach (['sso' => [10, 300], 'register' => [3, 100], 'password-reset-link' => [3, 100]] as $name => $expected) {
            $limits = RateLimiter::limiter($name)($request);
            $this->assertSame($expected, array_map(fn (Limit $limit) => $limit->maxAttempts, $limits), $name);
            $this->assertStringEndsWith('|203.0.113.5', $limits[1]->key, "{$name} : plafond par IP");
        }
        $this->assertSame('register|lea@exemple.fr', RateLimiter::limiter('register')($request)[0]->key);
    }

    public function test_a_whole_class_signs_in_with_the_school_account(): void
    {
        app(SettingService::class)->set('sso_driver', 'cas');
        app(SettingService::class)->set('cas_url', 'https://cas.exemple.fr/cas');

        for ($i = 1; $i <= self::CLASS_SIZE; $i++) {
            $this->nextStudent();
            $this->get(route('sso.redirect'))->assertRedirect();
            $this->get(route('sso.callback', ['state' => 'autre', 'ticket' => 'ST-1']))->assertRedirect(route('login'));
        }

        // Une meme personne (le meme cookie de session) qui recommence en boucle est freinee
        $session = Str::random(40);
        $statuses = collect(range(1, 11))->map(fn () => $this->withCookie(config('session.cookie'), $session)->get(route('sso.redirect'))->getStatusCode());
        $this->assertSame(429, $statuses->last());
    }

    public function test_a_whole_class_registers(): void
    {
        app(SettingService::class)->set('registration_open', '1');

        for ($i = 1; $i <= self::CLASS_SIZE; $i++) {
            $this->nextStudent();
            $this->post(route('register'), ['name' => "Élève {$i}", 'email' => "eleve{$i}@exemple.fr", 'classe' => '2nde A',
                'password' => 'Mot-De-Passe-2026', 'password_confirmation' => 'Mot-De-Passe-2026'])->assertRedirect(route('home'));
        }
        $this->assertDatabaseCount('users', self::CLASS_SIZE);
    }

    public function test_a_whole_class_asks_for_a_new_password_but_one_address_is_not_flooded(): void
    {
        Mail::fake();
        // 12 eleves (l'ancienne limite en bloquait 6) : chaque reponse de cette page est volontairement ralentie
        for ($i = 1; $i <= 12; $i++) {
            $this->student(['email' => "e{$i}@exemple.fr"]);
            $this->nextStudent();
            $this->post(route('password.email'), ['email' => "e{$i}@exemple.fr"])->assertSessionHasNoErrors()->assertRedirect();
        }

        $statuses = collect(range(1, 4))->map(fn () => $this->post(route('password.email'), ['email' => 'e1@exemple.fr'])->getStatusCode());
        $this->assertSame(429, $statuses->last());
    }
}
