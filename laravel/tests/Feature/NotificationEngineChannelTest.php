<?php

namespace Tests\Feature;

use App\Models\MaintenanceIssue;
use App\Models\User;
use App\Notifications\Channels\NotificationEngineChannel;
use App\Notifications\Maintenance\MaintenanceWorkAssigned;
use App\Services\NotificationEngine\ChannelDispatchResult;
use App\Services\NotificationEngine\NotificationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class NotificationEngineChannelTest extends TestCase
{
    use RefreshDatabase;

    private function issue(User $reporter): MaintenanceIssue
    {
        return MaintenanceIssue::create([
            'reference_number' => 'MNT-2026-0300', 'reporter_id' => $reporter->id, 'category' => 'drainage',
            'title' => 'Blocked drain', 'description' => 'Pooling water.', 'location' => 'Lot 3', 'priority' => 'medium', 'status' => 'reported',
        ]);
    }

    private function channelReturning(array $results): NotificationEngineChannel
    {
        $engine = $this->createMock(NotificationEngine::class);
        $engine->method('send')->willReturn($results);

        return new NotificationEngineChannel($engine);
    }

    public function test_an_unconfigured_provider_is_recorded_as_unavailable_not_failed(): void
    {
        $user = User::factory()->create();

        $this->channelReturning([
            'in_app' => ChannelDispatchResult::sent('in_app', '7'),
            'sms' => ChannelDispatchResult::unavailable('sms', 'Twilio not configured'),
        ])->send($user, new MaintenanceWorkAssigned($this->issue($user)));

        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'channel' => 'in_app', 'status' => 'sent']);
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'channel' => 'sms', 'status' => 'unavailable']);
    }

    public function test_when_every_channel_fails_the_job_fails_so_the_queue_retries_it(): void
    {
        $user = User::factory()->create();

        try {
            $this->channelReturning(['in_app' => ChannelDispatchResult::failed('in_app', 'database down')])
                ->send($user, new MaintenanceWorkAssigned($this->issue($user)));
            $this->fail('Expected the delivery to throw.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'status' => 'failed', 'error_message' => 'database down']);
        }
    }
}
