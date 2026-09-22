<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceReadOnly;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the promise the integration is built on: this API can read, and
 * cannot write.
 *
 * These tests read from the configured database and clean up the handful of
 * rows they create. They deliberately do NOT use RefreshDatabase, which would
 * wipe the developer's local data.
 */
class ChatGptReadOnlyApiTest extends TestCase
{
    private User $user;
    private string $readToken;
    private string $piiToken;
    private string $noAbilityToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'             => 'ChatGPT Test Integration',
            'email'            => 'chatgpt-test-' . Str::random(8) . '@example.test',
            'password'         => Hash::make(Str::random(32)),
            'is_master'        => false,
            'role_id'          => 0,
            'company_id'       => 0,
            'branch_id'        => 0,
            'employee_role_id' => 0,
        ]);

        $this->readToken      = $this->user->createToken('read', ['read'])->plainTextToken;
        $this->piiToken       = $this->user->createToken('pii', ['read', 'read-pii'])->plainTextToken;
        $this->noAbilityToken = $this->user->createToken('none', ['something-else'])->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->user->tokens()->delete();
        $this->user->delete();

        parent::tearDown();
    }

    /** @test */
    public function every_registered_chatgpt_route_is_get_only(): void
    {
        $offenders = collect(Route::getRoutes())
            ->filter(fn ($route) => Str::startsWith($route->uri(), 'api/chatgpt'))
            ->reject(fn ($route) => array_diff($route->methods(), ['GET', 'HEAD']) === [])
            ->map(fn ($route) => implode('|', $route->methods()) . ' ' . $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'A non-GET route is registered under api/chatgpt.');
    }

    /** @test */
    public function the_read_only_middleware_rejects_write_verbs(): void
    {
        $middleware = new EnforceReadOnly();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $middleware->handle(
                Request::create('/api/chatgpt/orders', $method),
                fn () => response()->json(['reached' => true])
            );

            $this->assertSame(403, $response->getStatusCode(), "{$method} was not rejected.");
        }

        $passed = $middleware->handle(
            Request::create('/api/chatgpt/orders', 'GET'),
            fn () => response()->json(['reached' => true])
        );

        $this->assertSame(200, $passed->getStatusCode());
    }

    /** @test */
    public function it_rejects_requests_without_a_token(): void
    {
        $this->getJson('/api/chatgpt/orders')->assertStatus(401);
    }

    /** @test */
    public function it_rejects_a_token_that_lacks_the_read_ability(): void
    {
        $this->withToken($this->noAbilityToken)
            ->getJson('/api/chatgpt/orders')
            ->assertStatus(403);
    }

    /** @test */
    public function it_returns_orders_to_a_read_token(): void
    {
        $this->withToken($this->readToken)
            ->getJson('/api/chatgpt/orders?per_page=1')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'reference', 'order_no', 'status', 'total']]]);
    }

    /** @test */
    public function customer_contact_details_are_masked_without_the_pii_ability(): void
    {
        $response = $this->withToken($this->readToken)
            ->getJson('/api/chatgpt/customers?per_page=1')
            ->assertOk();

        $customer = $response->json('data.0');

        if (! $customer || ! $customer['phone']) {
            $this->markTestSkipped('No customer with a phone number in this database.');
        }

        $this->assertStringContainsString('*', $customer['phone'], 'Phone was returned unmasked.');
        $this->assertStringContainsString('*', $customer['email'] ?? '*', 'Email was returned unmasked.');
    }

    /** @test */
    public function customer_contact_details_are_revealed_with_the_pii_ability(): void
    {
        $response = $this->withToken($this->piiToken)
            ->getJson('/api/chatgpt/customers?per_page=1')
            ->assertOk();

        $customer = $response->json('data.0');

        if (! $customer || ! $customer['phone']) {
            $this->markTestSkipped('No customer with a phone number in this database.');
        }

        $this->assertStringNotContainsString('*', $customer['phone']);
    }

    /** @test */
    public function an_unknown_reference_is_a_404_not_an_error(): void
    {
        $this->withToken($this->readToken)
            ->getJson('/api/chatgpt/orders/ORD-999999999')
            ->assertStatus(404);

        $this->withToken($this->readToken)
            ->getJson('/api/chatgpt/invoices/not-a-reference')
            ->assertStatus(404);
    }

    /** @test */
    public function per_page_cannot_be_used_to_dump_the_database(): void
    {
        $this->withToken($this->readToken)
            ->getJson('/api/chatgpt/orders?per_page=100000')
            ->assertStatus(422);
    }
}
