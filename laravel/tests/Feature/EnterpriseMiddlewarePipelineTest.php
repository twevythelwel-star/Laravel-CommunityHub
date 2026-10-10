<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnterpriseMiddlewarePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_web_requests_receive_generated_correlation_id(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $correlationId = $response->headers->get('X-Correlation-ID');
        $this->assertTrue(Str::isUuid($correlationId));
    }

    public function test_incoming_correlation_id_is_preserved_and_propagated(): void
    {
        $customId = (string) Str::uuid();

        $response = $this->withHeader('X-Correlation-ID', $customId)->get('/login');

        $response->assertOk();
        $this->assertSame($customId, $response->headers->get('X-Correlation-ID'));
    }

    public function test_community_context_is_resolved_and_bound_in_container(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertTrue($response->headers->has('X-Community-Code'));

        $this->assertInstanceOf(Community::class, app(Community::class));
    }

    public function test_explicit_community_header_resolves_targeted_community(): void
    {
        $secondary = Community::create([
            'name' => 'Royal Palms Enclave',
            'code' => 'CID-ROYAL-PALMS',
            'datum' => 'JAD2001',
        ]);

        $response = $this->withHeader('X-Community-Code', 'CID-ROYAL-PALMS')->get('/login');

        $response->assertOk();
        $this->assertSame('CID-ROYAL-PALMS', $response->headers->get('X-Community-Code'));
        $this->assertSame($secondary->id, app(Community::class)->id);
    }

    public function test_api_idempotency_key_replays_cached_response(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Homeowner,
        ]);

        $idempotencyKey = (string) Str::uuid();

        // First mutating request
        $firstResponse = $this->actingAs($user)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('/dashboard/profile/theme-preset', [
                'theme_preset' => 'copper',
            ]);

        $firstResponse->assertSessionHasNoErrors();
        $this->assertSame('false', $firstResponse->headers->get('X-Idempotent-Replayed'));
        $this->assertSame($idempotencyKey, $firstResponse->headers->get('Idempotency-Key'));

        // Second mutating request with identical Idempotency-Key
        $secondResponse = $this->actingAs($user)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('/dashboard/profile/theme-preset', [
                'theme_preset' => 'slate', // Differs, but should be ignored because key matches cached response
            ]);

        $this->assertSame('true', $secondResponse->headers->get('X-Idempotent-Replayed'));
        $this->assertSame($idempotencyKey, $secondResponse->headers->get('Idempotency-Key'));

        // User preference must remain 'copper' (from first request), proving deduplication
        $this->assertSame('copper', $user->fresh()->preferences->theme_preset);
    }
}
