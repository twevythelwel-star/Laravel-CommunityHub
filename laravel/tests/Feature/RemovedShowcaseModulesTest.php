<?php

namespace Tests\Feature;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The showcase hubs (queues, Octane, observability, feature flags, generic
 * PDFs, the query-builder playground and the "Filament" role simulator) were
 * removed: none backed an estate screen, and several could queue jobs, write
 * audit entries or issue documents in the estate's name. Even a System Admin
 * must find nothing at their old addresses.
 */
class RemovedShowcaseModulesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'queues' => ['/operations/queues'],
            'octane' => ['/operations/octane'],
            'observability' => ['/operations/observability'],
            'feature flags' => ['/operations/features'],
            'pdf' => ['/operations/pdf'],
            'query builder' => ['/query-builder'],
            'admin panel' => ['/admin'],
            'portal' => ['/portal'],
            'filament' => ['/filament'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_page_is_gone(string $uri): void
    {
        $this->actingAs(User::factory()->role(UserRole::SystemAdmin)->create())
            ->get($uri)
            ->assertNotFound();
    }

    /** @return array<string, array{string, string}> */
    public static function endpoints(): array
    {
        return [
            'queue dispatch' => ['POST', '/api/v1/queues/dispatch'],
            'queue stats' => ['GET', '/api/v1/queues/stats'],
            'octane benchmark' => ['POST', '/api/v1/octane/benchmark-concurrency'],
            'observability logs' => ['GET', '/api/v1/observability/logs'],
            'write audit' => ['POST', '/api/v1/observability/write-audit'],
            'simulate error' => ['POST', '/api/v1/observability/simulate-error'],
            'flags' => ['GET', '/api/v1/features'],
            'flag activate' => ['POST', '/api/v1/features/activate'],
            'pdf generate' => ['POST', '/api/v1/pdf/generate'],
            'pdf preview' => ['POST', '/api/v1/pdf/preview/receipt'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_the_endpoint_is_gone(string $method, string $uri): void
    {
        $root = User::factory()->role(UserRole::SystemAdmin)->create();
        $token = $root->createToken('Device', TokenAbility::forUser($root))->plainTextToken;

        $this->json($method, $uri, [], ['Authorization' => "Bearer {$token}"])->assertNotFound();
    }
}
