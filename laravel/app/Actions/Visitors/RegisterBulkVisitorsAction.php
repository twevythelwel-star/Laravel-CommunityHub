<?php

namespace App\Actions\Visitors;

use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Models\BlocklistEntry;
use App\Models\Renter;
use App\Models\User;
use App\Models\Visitor;
use App\Services\GatePassEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterBulkVisitorsAction
{
    public function __construct(
        private readonly GatePassEngine $engine,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{createdCount: int, blockedNames: array<string>, message: string}
     */
    public function execute(array $validated, User $user): array
    {
        $passCategory = PassCategory::from($validated['pass_category'] ?? PassCategory::Visitor->value);
        $expectedAt = Carbon::parse($validated['expected_at']);

        // Check stay timeframe for temporary residents
        if ($user->role === UserRole::TemporaryHomeowner) {
            $stay = $user->activeStay() ?? Renter::where('user_id', $user->id)->first();
            if (! $stay || $stay->leaseHasExpired()) {
                throw ValidationException::withMessages([
                    'expected_at' => 'Your temporary stay has expired or is inactive. You cannot register visitors.',
                ]);
            }

            $stayStart = $stay->lease_start->startOfDay();
            $stayEnd = $stay->lease_end->endOfDay();
            if ($expectedAt->lt($stayStart) || $expectedAt->gt($stayEnd)) {
                throw ValidationException::withMessages([
                    'expected_at' => "Visitors can only be scheduled within your approved stay timeframe ({$stay->lease_start->format('M d, Y')} to {$stay->lease_end->format('M d, Y')}).",
                ]);
            }
        }

        $createdCount = 0;
        $blockedNames = [];

        DB::transaction(function () use ($validated, $user, $expectedAt, $passCategory, &$createdCount, &$blockedNames) {
            foreach ($validated['visitors'] as $item) {
                $name = trim($item['name']);
                if (BlocklistEntry::blocks($name)) {
                    $blockedNames[] = $name;

                    continue;
                }

                $visitor = Visitor::create([
                    'homeowner_id' => $user->id,
                    'homeowner_name' => $user->display_name,
                    'name' => $name,
                    'contact' => $item['contact'] ?? null,
                    'vehicle' => $item['vehicle'] ?? null,
                    'type' => 'One-time',
                    'expected_at' => $expectedAt,
                    'status' => 'Expected',
                    'is_blocked' => false,
                    'notify_email' => $validated['notify_email'] ?? false,
                    'notify_sms' => $validated['notify_sms'] ?? false,
                    'notify_whatsapp' => $validated['notify_whatsapp'] ?? false,
                ]);

                $pass = $this->engine->issueGuestPass($visitor, $user, $passCategory);
                $createdCount++;

                $channels = $visitor->passNotificationChannels();
                if ($channels !== [] && $pass->status !== PassStatus::Requested) {
                    $visitor->sendPassNotification($channels);
                }
            }
        });

        $msg = "Registered {$createdCount} event attendee(s) with pre-cleared guest passes.";
        if (! empty($blockedNames)) {
            $msg .= ' Note: '.count($blockedNames).' attendee(s) could not be registered due to active community blocklist restrictions: '.implode(', ', $blockedNames);
        }

        return [
            'createdCount' => $createdCount,
            'blockedNames' => $blockedNames,
            'message' => $msg,
        ];
    }
}
