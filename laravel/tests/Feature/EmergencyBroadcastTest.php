<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VisitorStatus;
use App\Models\User;
use App\Models\Visitor;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_dispatch_emergency_broadcast_and_sms(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $resident = User::factory()->create([
            'role' => UserRole::Homeowner,
            'phone' => '+18765550201',
        ]);
        $visitor = Visitor::create([
            'homeowner_id' => $resident->id,
            'name' => 'Michael Scott',
            'type' => 'Visitor',
            'contact' => '+18765550202',
            'expected_at' => now(),
            'status' => VisitorStatus::CheckedIn,
        ]);

        $mockSms = $this->createMock(SmsService::class);
        $mockSms->method('isConfigured')->willReturn(true);
        $mockSms->expects($this->atLeastOnce())
            ->method('send')
            ->willReturn('SM_MOCK_BROADCAST');
        $this->app->instance(SmsService::class, $mockSms);

        $response = $this->actingAs($admin)->post('/dashboard/notifications/broadcast', [
            'title' => 'Severe Weather Warning',
            'content' => 'High winds expected near the main gate. Please stay indoors.',
            'severity' => 'emergency',
            'audience' => 'all',
            'send_sms' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', [
            'title' => '🚨 [EMERGENCY ALERT] Severe Weather Warning',
            'content' => 'High winds expected near the main gate. Please stay indoors.',
        ]);
    }

    public function test_homeowner_cannot_dispatch_emergency_broadcast(): void
    {
        $homeowner = User::factory()->create(['role' => UserRole::Homeowner]);

        $response = $this->actingAs($homeowner)->post('/dashboard/notifications/broadcast', [
            'title' => 'Test',
            'content' => 'Unauthorized broadcast',
            'severity' => 'info',
            'audience' => 'all',
        ]);

        $response->assertForbidden();
    }
}
