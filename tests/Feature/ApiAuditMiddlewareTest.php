<?php

namespace Tests\Feature;

use App\Http\Middleware\LogAnonymousApiRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * The audit middleware sits on the global `api` group, so it runs on every
 * single API request. These tests pin down that it stays invisible: it must
 * not change a response, must not query the database, and must not be able to
 * fill the disk.
 */
class ApiAuditMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /** @test */
    public function it_does_not_change_the_response_of_an_existing_open_route(): void
    {
        $response = $this->getJson('/api/product-list');

        $response->assertOk();
        $this->assertNotEmpty($response->getContent());
    }

    /** @test */
    public function it_passes_the_request_through_untouched(): void
    {
        $middleware = new LogAnonymousApiRequests();
        $expected   = response()->json(['value' => 'unchanged'], 201);

        $actual = $middleware->handle(Request::create('/api/anything'), fn () => $expected);

        $this->assertSame($expected, $actual);
    }

    /** @test */
    public function it_skips_requests_that_carry_a_token_without_touching_the_database(): void
    {
        Log::shouldReceive('channel')->never();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $request = Request::create('/api/customers', 'GET');
        $request->headers->set('Authorization', 'Bearer some-token-value');

        (new LogAnonymousApiRequests())->terminate($request, response()->json([]));

        $this->assertSame(0, $queries, 'The audit middleware queried the database on an authenticated request.');
    }

    /** @test */
    public function it_records_an_anonymous_request(): void
    {
        $logger = Mockery::spy(\Psr\Log\LoggerInterface::class);
        Log::shouldReceive('channel')->with('api_audit')->andReturn($logger);

        (new LogAnonymousApiRequests())->terminate(
            Request::create('/api/customers', 'GET'),
            response()->json([])
        );

        $logger->shouldHaveReceived('info')->once();
    }

    /** @test */
    public function it_records_the_same_route_and_ip_only_once_per_window(): void
    {
        $logger = Mockery::spy(\Psr\Log\LoggerInterface::class);
        Log::shouldReceive('channel')->with('api_audit')->andReturn($logger);

        $middleware = new LogAnonymousApiRequests();

        for ($i = 0; $i < 25; $i++) {
            $middleware->terminate(
                Request::create('/api/customers', 'GET'),
                response()->json([])
            );
        }

        $logger->shouldHaveReceived('info')->once();
    }

    /** @test */
    public function it_never_lets_a_logging_failure_reach_the_caller(): void
    {
        Log::shouldReceive('channel')->andThrow(new \RuntimeException('disk full'));

        (new LogAnonymousApiRequests())->terminate(
            Request::create('/api/customers', 'GET'),
            response()->json([])
        );

        // Reaching this line without an exception is the assertion.
        $this->assertTrue(true);
    }

    /** @test */
    public function it_does_not_log_routes_that_are_anonymous_by_design(): void
    {
        Log::shouldReceive('channel')->never();

        $middleware = new LogAnonymousApiRequests();

        foreach (['/api/login', '/api/top-menu', '/api/side-menu', '/api/chatgpt/orders'] as $path) {
            $middleware->terminate(Request::create($path, 'GET'), response()->json([]));
        }
    }
}
