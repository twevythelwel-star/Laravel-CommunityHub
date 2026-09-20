<?php

namespace Tests\Feature;

use App\Enums\VisitorStatus;
use App\Models\Community;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestPassTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_automatically_receives_share_token(): void
    {
        $homeowner = User::factory()->create();

        $visitor = Visitor::create([
            'name' => 'Michael Chang',
            'type' => 'One-time',
            'homeowner_id' => $homeowner->id,
            'homeowner_name' => $homeowner->name,
            'status' => VisitorStatus::Expected,
            'expected_at' => now()->addHours(2),
        ]);

        $this->assertNotNull($visitor->share_token);
        $this->assertEquals(32, strlen($visitor->share_token));
    }

    public function test_guest_pass_view_is_publicly_accessible(): void
    {
        $community = Community::create([
            'name' => 'Cypress Bay',
            'code' => 'CYPRESS-01',
            'slug' => 'cypress-bay',
            'domain' => 'cypressbay.communityhub.org',
            'center_latitude' => 18.0,
            'center_longitude' => -76.8,
        ]);

        $homeowner = User::factory()->create(['name' => 'Marcus Vance', 'lot' => 'Lot 42']);

        $visitor = Visitor::create([
            'name' => 'Michael Chang',
            'type' => 'One-time',
            'homeowner_id' => $homeowner->id,
            'homeowner_name' => $homeowner->name,
            'status' => VisitorStatus::Expected,
            'expected_at' => now()->addHours(3),
        ]);

        $response = $this->get(route('guest-pass.show', ['token' => $visitor->share_token]));

        $response->assertOk();
        $response->assertSee('Michael Chang');
        $response->assertSee('Marcus Vance');
        $response->assertSee('Digital Guest Pass');
    }

    public function test_invalid_token_returns_404(): void
    {
        $response = $this->get(route('guest-pass.show', ['token' => 'invalid-token-123']));
        $response->assertNotFound();
    }

    public function test_visitor_pdf_download_streams_pdf(): void
    {
        $homeowner = User::factory()->create();
        $visitor = Visitor::create([
            'name' => 'Michael Chang',
            'type' => 'One-time',
            'homeowner_id' => $homeowner->id,
            'homeowner_name' => $homeowner->name,
            'status' => VisitorStatus::Expected,
            'expected_at' => now()->addHours(2),
        ]);

        $response = $this->get(route('pdf.visitor-pass', ['visitor' => $visitor->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
