<?php

namespace Tests\Feature\Api;

use App\Services\ApiTokenService;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/** Garantit que public/docs/openapi.yaml decrit l'API telle qu'elle repond reellement. */
class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spec = Yaml::parseFile(public_path('docs/openapi.yaml'));
    }

    /** @return array<string, Route> "METHODE /chemin" => route Laravel */
    private function apiRoutes(): array
    {
        $routes = [];
        foreach (RouteFacade::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $routes[$method . ' /' . substr($route->uri(), strlen('api/v1/'))] = $route;
            }
        }

        return $routes;
    }

    /** @return list<string> "METHODE /chemin" decrits par le fichier OpenAPI */
    private function documentedOperations(): array
    {
        $operations = [];
        foreach ($this->spec['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $operations[] = strtoupper($method) . ' ' . $path;
            }
        }

        return $operations;
    }

    public function test_every_route_is_documented_and_every_documented_operation_exists(): void
    {
        $documented = $this->documentedOperations();
        $actual = array_keys($this->apiRoutes());
        sort($documented);
        sort($actual);

        $this->assertSame($actual, $documented);
    }

    public function test_the_required_ability_matches_the_route_middleware(): void
    {
        foreach ($this->apiRoutes() as $operation => $route) {
            [$method, $path] = explode(' ', $operation);
            $documentedOperation = $this->spec['paths'][$path][strtolower($method)];
            $abilities = collect($route->gatherMiddleware())
                ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'abilities:'))
                ->map(fn ($middleware) => substr($middleware, strlen('abilities:')))
                ->values()->all();

            $this->assertSame($abilities[0] ?? null, $documentedOperation['x-required-ability'] ?? null, "Droit requis pour {$operation}");
            $this->assertSame($path === '/meta', ($documentedOperation['security'] ?? null) === [], "Securite documentee pour {$operation}");
        }
    }

    public function test_documented_abilities_are_the_ones_a_token_can_receive(): void
    {
        $documented = $this->spec['components']['schemas']['Me']['properties']['abilities']['items']['enum'];

        $this->assertEqualsCanonicalizing(array_keys(ApiTokenService::ABILITIES), $documented);
    }

    public function test_real_responses_match_the_documented_schemas(): void
    {
        $resource = Resource::create(['name' => 'CDI', 'description' => null, 'open_time' => '08:00', 'close_time' => '23:59', 'closed_days' => [6, 7]]);
        $slot = Slot::create(['resource_id' => $resource->id, 'name' => 'Recre', 'start_time' => '23:00', 'end_time' => '23:30', 'max_capacity' => 3]);
        $user = User::factory()->create(['role_id' => Role::defaultUser()->id, 'classe' => null]);
        $reservation = Reservation::create(['user_id' => $user->id, 'slot_id' => $slot->id, 'reservation_date' => now()->addDay()->toDateString()]);
        $headers = ['Authorization' => 'Bearer ' . $user->createToken('contract', ['resources:read', 'reservations:read'])->plainTextToken];
        $date = now()->addDay()->toDateString();

        $calls = [
            '/meta' => '/api/v1/meta',
            '/me' => '/api/v1/me',
            '/resources' => '/api/v1/resources',
            '/resources/{resource}' => "/api/v1/resources/{$resource->id}",
            '/resources/{resource}/slots' => "/api/v1/resources/{$resource->id}/slots",
            '/availability' => "/api/v1/availability?date={$date}",
            '/rules' => '/api/v1/rules',
            '/reservations' => '/api/v1/reservations',
            '/reservations/{reservation}' => "/api/v1/reservations/{$reservation->id}",
        ];
        $getPaths = array_keys(array_filter($this->spec['paths'], fn ($methods) => isset($methods['get'])));
        $this->assertEqualsCanonicalizing($getPaths, array_keys($calls), 'Un endpoint GET n\'a pas de test de contrat');

        foreach ($calls as $path => $url) {
            $body = $this->getJson($url, $headers)->assertOk()->json();
            $schema = $this->spec['paths'][$path]['get']['responses']['200']['content']['application/json']['schema'];
            $this->assertMatchesSchema($body, $schema, $path);
        }

        // Ecritures : creation (201, schema Reservation) puis annulation (204 sans corps)
        $writer = ['Authorization' => 'Bearer ' . $user->createToken('writer', ['reservations:write'])->plainTextToken];
        $this->app['auth']->forgetGuards(); // chaque requete reelle repart d'un garde vierge
        $created = $this->postJson('/api/v1/reservations', ['slot_id' => $slot->id, 'date' => now()->addDays(3)->toDateString()], $writer)->assertCreated();
        $schema = $this->spec['paths']['/reservations']['post']['responses']['201']['content']['application/json']['schema'];
        $this->assertMatchesSchema($created->json(), $schema, 'POST /reservations');
        $this->assertSame(['slot_id', 'date'], $this->spec['components']['schemas']['ReservationInput']['required']);

        $this->deleteJson('/api/v1/reservations/' . $created->json('data.id'), [], $writer)->assertNoContent();
    }

    public function test_error_responses_match_the_documented_error_schema(): void
    {
        $errorSchema = ['$ref' => '#/components/schemas/Error'];
        $user = User::factory()->create();
        $limited = ['Authorization' => 'Bearer ' . $user->createToken('c', ['reservations:read'])->plainTextToken];
        $full = ['Authorization' => 'Bearer ' . $user->createToken('c', ['resources:read'])->plainTextToken];

        $cases = [
            [401, '/api/v1/me', []],
            [403, '/api/v1/resources', $limited],
            [404, '/api/v1/resources/999', $full],
            [422, '/api/v1/availability', $full],
        ];
        foreach ($cases as [$status, $url, $headers]) {
            $this->app['auth']->forgetGuards(); // chaque requete reelle repart d'un garde vierge
            $this->assertMatchesSchema($this->getJson($url, $headers)->assertStatus($status)->json(), $errorSchema, (string) $status);
        }
    }

    public function test_the_docs_page_is_served_without_external_assets(): void
    {
        $html = $this->get('/api/docs')->assertOk()->getContent();

        $this->assertStringContainsString('vendor/swagger-ui/swagger-ui-bundle.js', $html);
        $this->assertStringContainsString('docs/openapi.yaml', $html);
        $this->assertStringNotContainsString('cdn.', $html);
        $this->assertFileExists(public_path('vendor/swagger-ui/swagger-ui-bundle.js'));
    }

    // ---- mini validateur de schema : types, proprietes, valeurs autorisees ----------------------

    private function resolveRef(array $schema): array
    {
        while (isset($schema['$ref'])) {
            $node = $this->spec;
            foreach (explode('/', substr($schema['$ref'], 2)) as $segment) {
                $node = $node[$segment];
            }
            $schema = $node;
        }

        return $schema;
    }

    private function assertMatchesSchema(mixed $value, array $schema, string $where): void
    {
        $schema = $this->resolveRef($schema);
        $types = (array) ($schema['type'] ?? []);

        if ($types !== []) {
            $this->assertTrue($this->hasType($value, $types), "{$where} : type attendu " . implode('|', $types) . ', recu ' . get_debug_type($value));
        }
        if (array_key_exists('enum', $schema)) {
            $this->assertContains($value, $schema['enum'], "{$where} : valeur hors enum");
        }

        if (is_array($value) && array_is_list($value) && isset($schema['items'])) {
            foreach ($value as $i => $item) {
                $this->assertMatchesSchema($item, $schema['items'], "{$where}[{$i}]");
            }
        } elseif (is_array($value) && !array_is_list($value)) {
            $properties = $schema['properties'] ?? null;
            foreach ($schema['required'] ?? [] as $key) {
                $this->assertArrayHasKey($key, $value, "{$where} : champ requis absent : {$key}");
            }
            if ($properties !== null) {
                $this->assertSame([], array_values(array_diff(array_keys($value), array_keys($properties))), "{$where} : champs non documentes");
                foreach ($value as $key => $item) {
                    $this->assertMatchesSchema($item, $properties[$key], "{$where}.{$key}");
                }
            } elseif (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
                foreach ($value as $key => $item) {
                    $this->assertMatchesSchema($item, $schema['additionalProperties'], "{$where}.{$key}");
                }
            }
        }
    }

    private function hasType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            $ok = match ($type) {
                'null' => $value === null,
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'array' => is_array($value) && ($value === [] || array_is_list($value)),
                'object' => is_array($value) && ($value === [] || !array_is_list($value)),
                default => false,
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }
}
