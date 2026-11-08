<?php

namespace Tests\Feature;

use App\Livewire\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ACC-2: who may reach each route. Later tasks append their routes and Livewire actions here.
 */
class RoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * [method, uri, guest, owner, kasir, inactive kasir]. An int is the expected status;
     * a string is the path of the expected redirect.
     */
    private const ROUTES = [
        ['GET', '/up', 200, 200, 200, 200],
        ['GET', '/login', 200, '/', '/', '/'],
        ['POST', '/login', 302, '/', '/', '/'],
        ['POST', '/logout', '/login', '/login', '/login', '/login'],
        ['GET', '/', '/login', 200, '/pos', '/login'],
        ['GET', '/pos', '/login', 200, 200, '/login'],
        ['GET', '/staff', '/login', 200, 403, '/login'],
    ];

    /** Owner-only Livewire components and every action they expose. */
    private const OWNER_COMPONENTS = [
        '/staff' => [Staff::class, ['create', 'edit', 'save', 'cancel', 'confirmDeactivate', 'deactivate', 'activate']],
    ];

    public static function routeCases(): iterable
    {
        foreach (self::ROUTES as [$method, $uri, $guest, $owner, $kasir, $inactive]) {
            foreach (compact('guest', 'owner', 'kasir', 'inactive') as $who => $expected) {
                yield "$method $uri as $who" => [$method, $uri, $who, $expected];
            }
        }
    }

    #[DataProvider('routeCases')]
    public function test_each_role_gets_the_expected_answer(string $method, string $uri, string $who, int|string $expected): void
    {
        $user = match ($who) {
            'guest' => null,
            'owner' => User::factory()->owner()->create(),
            'kasir' => User::factory()->kasir()->create(),
            'inactive' => User::factory()->kasir()->inactive()->create(),
        };

        if ($user) {
            $this->actingAs($user);
        }

        $response = $this->call($method, $uri);

        is_int($expected) ? $response->assertStatus($expected) : $response->assertRedirect($expected);
    }

    public function test_every_route_is_in_the_table(): void
    {
        $listed = array_map(fn (array $row) => $row[0].' '.$row[1], self::ROUTES);

        $missing = collect(Route::getRoutes()->getRoutes())
            // Livewire's own endpoints re-apply the page route's middleware (see the Livewire tests below).
            ->reject(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'livewire-') || str_starts_with($route->uri(), 'storage/'))
            ->map(fn (RoutingRoute $route) => $route->methods()[0].' /'.ltrim($route->uri(), '/'))
            ->diff($listed)
            ->values()
            ->all();

        $this->assertSame([], $missing, 'Add these routes to RoleMatrixTest::ROUTES.');
    }

    public static function ownerComponents(): iterable
    {
        foreach (self::OWNER_COMPONENTS as $path => [$component, $actions]) {
            yield $path => [$path, $component, $actions];
        }
    }

    #[DataProvider('ownerComponents')]
    public function test_the_component_refuses_a_kasir_on_mount_and_on_every_action(string $path, string $component, array $actions): void
    {
        $kasir = User::factory()->kasir()->create();

        Livewire::actingAs($kasir)->test($component)->assertForbidden();

        foreach ($actions as $action) {
            $testable = Livewire::actingAs(User::factory()->owner()->create())->test($component);
            $this->actingAs($kasir);

            $testable->call($action, $kasir->id)->assertForbidden();
        }
    }

    /** A Kasir holding an Owner page's snapshot still cannot call its actions over HTTP. */
    #[DataProvider('ownerComponents')]
    public function test_livewire_requests_refuse_a_kasir(string $path, string $component, array $actions): void
    {
        $owner = User::factory()->owner()->create();
        $snapshot = $this->snapshotOf($this->actingAs($owner)->get($path));

        // Control: the same request works for the Owner, so the 403s below are about the role.
        $this->callAction($snapshot, '$refresh')->assertOk();

        $this->actingAs(User::factory()->kasir()->create());

        foreach ($actions as $action) {
            $this->callAction($snapshot, $action)->assertForbidden();
        }
    }

    public function test_livewire_requests_sign_out_a_deactivated_user(): void
    {
        $owner = User::factory()->owner()->create();
        $snapshot = $this->snapshotOf($this->actingAs($owner)->get('/staff'));

        $owner->forceFill(['active' => false])->save();

        $this->callAction($snapshot, '$refresh')->assertRedirect('/login');
        $this->assertGuest();
    }

    private function snapshotOf(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $match), 'The page has no Livewire component.');

        return html_entity_decode($match[1]);
    }

    private function callAction(string $snapshot, string $method): TestResponse
    {
        return $this->withHeaders(['X-Livewire' => '1'])->postJson(app(HandleRequests::class)->getUpdateUri(), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) [],
                'calls' => [['method' => $method, 'params' => [], 'metadata' => (object) []]],
            ]],
        ]);
    }
}
