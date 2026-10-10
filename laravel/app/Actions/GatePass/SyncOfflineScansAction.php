<?php

namespace App\Actions\GatePass;

use App\Enums\GateId;
use App\Enums\PassStatus;
use App\Events\Realtime\OperationsCommandCenterEvent;
use App\Models\AccessLogEntry;
use App\Models\GatePass;
use App\Models\User;
use App\Services\GatePassEngine;
use Carbon\Carbon;

class SyncOfflineScansAction
{
    public function __construct(
        protected GatePassEngine $engine,
    ) {}

    /**
     * Reconcile queued offline scans recorded by gate guards during connectivity loss.
     *
     * @param  array<int, array{offline_id: string, token_or_pin: string, method?: string, scanned_at?: string, action?: string, notes?: string}>  $scans
     * @return array<string, mixed>
     */
    public function execute(array $scans, ?string $gateStr, ?User $guard = null): array
    {
        $gate = GateId::tryFrom($gateStr ?? '') ?? GateId::Gate01;

        $processed = 0;
        $accepted = 0;
        $rejected = 0;
        $duplicatesSkipped = 0;
        $entries = [];

        foreach ($scans as $scan) {
            $offlineId = $scan['offline_id'] ?? null;
            if (! $offlineId) {
                continue;
            }

            // Deduplication: check if already ingested
            $alreadyExists = AccessLogEntry::whereJsonContains('validation_report->offline_id', $offlineId)->exists();
            if ($alreadyExists) {
                $duplicatesSkipped++;

                continue;
            }

            $processed++;
            $rawToken = trim((string) ($scan['token_or_pin'] ?? ''));
            $method = $scan['method'] ?? 'Offline Pass';
            $scannedAt = ! empty($scan['scanned_at']) ? Carbon::parse($scan['scanned_at']) : Carbon::now();
            $action = strtolower($scan['action'] ?? 'check_in');

            $candidatePass = null;
            $offlinePinScan = false;
            $ambiguousPin = false;
            $tokenRejected = false;
            $decision = 'REJECT';
            $resultStatus = 'DENY';
            $denyReason = null;
            $userName = 'Unknown Visitor';
            $userRole = 'Visitor';
            $userId = null;

            // Check if 6-digit offline PIN
            $pinClean = trim(str_replace('-', '', $rawToken));
            if (preg_match('/^\d{6}$/', $pinClean)) {
                $offlinePinScan = true;
                $method = 'Offline Gate PIN';
                $pinMatches = GatePass::with(['user', 'visitor'])
                    ->whereIn('status', [PassStatus::Active, PassStatus::CheckedIn])
                    ->get()
                    ->filter(fn ($p) => $p->offline_pin === $pinClean);

                // The PIN is derived from the pass, so two active passes can share
                // one. The gate let someone in, but the PIN cannot say who: taking
                // the first match would record (and move) the wrong person's pass.
                $candidatePass = $pinMatches->count() === 1 ? $pinMatches->first() : null;
                $ambiguousPin = $pinMatches->count() > 1;
            } else {
                // Try validating as token or pass_id
                $report = $this->engine->validate($rawToken, $gate);
                if (! empty($report['passId'])) {
                    $candidatePass = GatePass::with(['user', 'visitor'])
                        ->where('pass_id', $report['passId'])
                        ->first();
                    $decision = $report['decision'] ?? 'REJECT';
                    $resultStatus = $report['status'] ?? 'DENY';
                    $denyReason = $report['primaryReason'] ?? null;
                    $tokenRejected = $decision === 'REJECT' || $resultStatus === 'DENY';
                }
            }

            if ($candidatePass) {
                $userName = $candidatePass->user?->name ?? $candidatePass->visitor?->name ?? 'Guest';
                $userRole = $candidatePass->user?->role->value ?? $candidatePass->category->value;
                $userId = $candidatePass->user_id ?? $candidatePass->visitor?->homeowner_id;

                // Reconciliation always honors the live token validation. A
                // revoked/superseded token may still decode to a registry row;
                // finding that row must never turn a rejected token into access.
                if (! $offlinePinScan && $tokenRejected) {
                    $rejected++;
                    // Validate transition
                } elseif ($action === 'check_in' && $candidatePass->canTransitionTo(PassStatus::CheckedIn)) {
                    $candidatePass->transitionTo(PassStatus::CheckedIn, $guard, 'Offline gate check-in synced', $gate);
                    $decision = 'CHECK_IN';
                    $resultStatus = 'PERMITTED';
                    $accepted++;
                } elseif ($action === 'check_out' && $candidatePass->canTransitionTo(PassStatus::CheckedOut)) {
                    $candidatePass->transitionTo(PassStatus::CheckedOut, $guard, 'Offline gate check-out synced', $gate);
                    $decision = 'CHECK_OUT';
                    $resultStatus = 'PERMITTED';
                    $accepted++;
                } else {
                    $decision = 'PERMITTED';
                    $resultStatus = 'PERMITTED';
                    $accepted++;
                }
            } elseif ($ambiguousPin) {
                $rejected++;
                $denyReason = 'This PIN matches more than one pass, so the offline entry cannot be attributed to anyone. Check the gate log or camera for who entered.';
            } else {
                $rejected++;
                $denyReason = 'Offline PIN or Pass Token not found or expired.';
            }

            $entry = AccessLogEntry::create([
                'user_id' => $userId,
                'user_name' => $userName,
                'user_role' => $userRole,
                'method' => $method,
                'gate' => $gate->label(),
                // Never the PIN itself when it is shared: it still opens the gate for two people.
                'pass_id' => $candidatePass?->pass_id ?? ($ambiguousPin ? 'OFFLINE-SHARED-PIN' : ($rawToken ?: 'OFFLINE-UNKNOWN')),
                'result' => $resultStatus,
                'deny_reason' => $denyReason,
                'validation_report' => array_filter([
                    'offline_id' => $offlineId,
                    'decision' => $decision,
                    'offline_synced' => true,
                    'notes' => $scan['notes'] ?? null,
                ]),
                'scanned_by' => $guard?->id,
                'occurred_at' => $scannedAt,
            ]);


            $entries[] = [
                'offline_id' => $offlineId,
                'access_log_id' => $entry->id,
                'status' => $resultStatus,
                'decision' => $decision,
            ];
        }

        if ($processed > 0) {
            $operatorName = $guard?->name ?? 'Automated Gate Controller';
            OperationsCommandCenterEvent::dispatch(
                alertId: 'SYNC-'.strtoupper(bin2hex(random_bytes(3))),
                type: 'gate_traffic',
                severity: 'info',
                headline: sprintf('Offline Gate Pass Sync: %d scan(s) reconciled at %s', $processed, $gate->label()),
                location: $gate->label(),
                operatorName: $operatorName,
                details: [
                    'gate' => $gate->value,
                    'processed' => $processed,
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                    'duplicates_skipped' => $duplicatesSkipped,
                ]
            );
        }

        return [
            'success' => true,
            'gate' => $gate->value,
            'processed' => $processed,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'duplicates_skipped' => $duplicatesSkipped,
            'synced_at' => now()->toIso8601String(),
            'entries' => $entries,
        ];
    }
}
