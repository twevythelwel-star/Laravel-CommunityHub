<?php

namespace Tests\Feature;

use App\Enums\GateId;
use App\Enums\UserRole;
use App\Events\AccessLogRecorded;
use App\Models\AccessLogEntry;
use App\Models\User;
use App\Services\GatePassEngine;
use App\Services\GateScanner;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * Every access-log line reaches the gate kiosks live, resident scans
 * included, and only as the row the kiosk shows.
 */
class GateActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $announced = [];

    private User $guard;

    protected function setUp(): void
    {
        parent::setUp();

        // Inside every shift in config/gatepass.php.
        $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00:00'));
        $this->guard = User::factory()->role(UserRole::Security)->create();

        // A real listener rather than Event::fake(), so after-commit applies.
        Event::listen(AccessLogRecorded::class, function (AccessLogRecorded $event): void {
            $this->announced[] = $event->broadcastWith();
        });
    }

    public function test_a_resident_check_in_is_announced_when_scanned_and_when_confirmed(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create(['display_name' => 'Olivia Davis']);
        $engine = app(GatePassEngine::class);
        $scanner = app(GateScanner::class);

        $scan = $scanner->scan($engine->issueToken($engine->issuePassFor($resident))['token'], GateId::Gate01, $this->guard);
        $scanner->confirm($scan['scanId'], $this->guard);

        $this->assertSame(['ALLOW', 'CHECK_IN'], array_column($this->announced, 'result'));
        $this->assertSame(['Olivia Davis', 'Olivia Davis'], array_column($this->announced, 'userName'));
        $this->assertSame(UserRole::Homeowner->value, $this->announced[0]['userRole']);
    }

    public function test_a_refused_scan_is_announced_with_its_reason(): void
    {
        app(GateScanner::class)->scan('not-a-real-token', GateId::Gate02, $this->guard);

        $this->assertCount(1, $this->announced);
        $this->assertSame('DENY', $this->announced[0]['result']);
        $this->assertNotEmpty($this->announced[0]['denyReason']);
    }

    public function test_the_announcement_is_the_kiosk_row_and_nothing_more(): void
    {
        $entry = AccessLogEntry::create([
            'user_id' => User::factory()->create()->id,
            'user_name' => 'Jane Doe', 'user_role' => 'Homeowner', 'method' => 'QR',
            'gate' => 'Main Gate', 'pass_id' => 'GP-123', 'result' => 'DENY',
            'deny_reason' => 'TOKEN_EXPIRED', 'validation_report' => ['signature' => 'secret-detail'],
            'scanned_by' => $this->guard->id, 'occurred_at' => now(),
        ]);

        $event = new AccessLogRecorded($entry);

        $this->assertSame(
            ['id', 'userName', 'userRole', 'result', 'gate', 'occurredAt', 'denyReason', 'confirms', 'confirmBy'],
            array_keys($event->broadcastWith()),
        );
        $this->assertStringNotContainsString('secret-detail', json_encode($event->broadcastWith()));
        $this->assertSame('access.recorded', $event->broadcastAs());
        $this->assertEquals([new PrivateChannel('gatehouse-stream')], $event->broadcastOn());
    }

    public function test_an_entry_rolled_back_with_its_transaction_is_never_announced(): void
    {
        try {
            DB::transaction(function (): void {
                AccessLogEntry::create([
                    'user_name' => 'Ghost', 'user_role' => 'Visitor', 'method' => 'QR', 'gate' => 'Main Gate',
                    'result' => 'ALLOW', 'scanned_by' => $this->guard->id, 'occurred_at' => now(),
                ]);

                throw new RuntimeException('Check-in failed after logging.');
            });
        } catch (RuntimeException) {
            // Expected: the whole check-in, log line included, is undone.
        }

        $this->assertSame(0, AccessLogEntry::count());
        $this->assertSame([], $this->announced);
    }
}
