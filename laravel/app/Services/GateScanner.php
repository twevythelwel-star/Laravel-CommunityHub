<?php

namespace App\Services;

use App\Enums\GateId;
use App\Enums\PassStatus;
use App\Enums\ScanDecision;
use App\Enums\ValidationStatus;
use App\Events\VisitorCheckedInEvent;
use App\Exceptions\InvalidPassTransition;
use App\Exceptions\ScanNotConfirmable;
use App\Models\AccessLogEntry;
use App\Models\BlocklistEntry;
use App\Models\GatePass;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The guard's side of the gate: scan, then confirm or refuse.
 *
 * scan() runs the engine and, for a CHECK_IN or CHECK_OUT decision, parks the
 * decision under a random scan ID for a short time. confirm() applies it. The
 * confirm call carries only that ID. The action comes from the server's own
 * record, so the browser cannot choose to check someone in or out, and a
 * decision can be confirmed once, by the guard who scanned, before it lapses.
 *
 * Replaces GatePassController::confirmAction(), which took a pass ID and an
 * action straight from the client with no token and no validation.
 */
class GateScanner
{
    public function __construct(private readonly GatePassEngine $engine) {}

    /**
     * @return array{report: array<string, mixed>, decision: string, scanId: string|null, accessLogId: int, expiresAt: string|null}
     */
    public function scan(string $token, GateId $gate, User $guard, string $method = 'Digital Pass'): array
    {
        $trimmed = trim(str_replace('-', '', $token));
        if (preg_match('/^\d{6}$/', $trimmed)) {
            $candidate = GatePass::whereIn('status', [PassStatus::Active, PassStatus::CheckedIn])
                ->get()
                ->first(fn ($p) => $p->offline_pin === $trimmed);

            if ($candidate) {
                $issued = $this->engine->issueToken($candidate, $gate);
                $token = $issued['token'];
                $method = 'Offline Gate PIN';
            }
        }

        $report = $this->engine->validate($token, $gate);
        $pass = GatePass::with(['user', 'visitor'])->where('pass_id', $report['passId'])->first();
        $decision = ScanDecision::from($report['decision']);

        $entry = AccessLogEntry::create([
            'user_id' => $pass?->user_id ?? $pass?->visitor?->homeowner_id,
            'user_name' => $report['userName'],
            'user_role' => $pass?->user?->role->value ?? $report['category'],
            'method' => $method,
            'gate' => $this->gateName($gate),
            'pass_id' => $report['passId'],
            'result' => $report['status'],
            'deny_reason' => $decision === ScanDecision::Reject ? $report['primaryReason'] : null,
            'validation_report' => $report,
            'scanned_by' => $guard->id,
            'occurred_at' => now(),
        ]);

        if ($decision === ScanDecision::Reject) {
            Log::channel('security')->warning('Gate pass rejected', [
                'pass_id' => $report['passId'],
                'gate' => $gate->value,
                'reason' => $report['primaryReason'],
                'scanned_by' => $guard->uid,
            ]);

            return ['report' => $report, 'decision' => $decision->value, 'scanId' => null, 'accessLogId' => $entry->id, 'expiresAt' => null];
        }

        $ttl = (int) config('gatepass.scan_confirm_seconds', 120);
        $scanId = (string) Str::uuid();

        Cache::put($this->cacheKey($scanId), [
            'pass_id' => $pass->id,
            'decision' => $decision->value,
            'from_status' => $pass->status->value,
            'gate' => $gate->value,
            'guard_id' => $guard->id,
            'method' => $method,
        ], $ttl);

        return [
            'report' => $report,
            'decision' => $decision->value,
            'scanId' => $scanId,
            'accessLogId' => $entry->id,
            'expiresAt' => now()->addSeconds($ttl)->toIso8601String(),
        ];
    }

    /**
     * Applies (or, with $accept false, refuses) a scanned decision.
     *
     * @return array{action: string, passStatus: string, time: string, message: string}
     *
     * @throws ScanNotConfirmable
     */
    public function confirm(string $scanId, User $guard, bool $accept = true, ?string $refusalReason = null): array
    {
        // pull, not get: a decision is used up by the first attempt to confirm it.
        $pending = Cache::pull($this->cacheKey($scanId));

        if (! is_array($pending) || $pending['guard_id'] !== $guard->id) {
            // Unknown, lapsed, already used, or someone else's scan: all the same to the caller.
            throw new ScanNotConfirmable('This scan has expired or was already confirmed. Scan the pass again.');
        }

        $pass = GatePass::with(['user', 'visitor'])->findOrFail($pending['pass_id']);
        $gate = GateId::from($pending['gate']);
        $decision = ScanDecision::from($pending['decision']);
        $now = now();

        if (! $accept) {
            $this->log($pass, $gate, $guard, ValidationStatus::Deny->value, $pending['method'],
                'REFUSED_BY_OFFICER: '.($refusalReason ?: 'Guard refused entry after inspection.'));

            return ['action' => 'REFUSED', 'passStatus' => $pass->status->value, 'time' => $now->format('g:i A'), 'message' => 'Entry refused and logged.'];
        }

        // The pass may have been revoked, suspended or used since the scan.
        if ($pass->status->value !== $pending['from_status']) {
            throw new ScanNotConfirmable(sprintf('The pass changed to %s since it was scanned. Scan it again.', $pass->status->label()));
        }

        try {
            if ($decision === ScanDecision::CheckIn && $pass->status === PassStatus::Issued) {
                $pass->transitionTo(PassStatus::Active, $guard, 'First use at the gate', $gate);
            }

            $pass->transitionTo($decision->targetStatus(), $guard, null, $gate);
        } catch (InvalidPassTransition $e) {
            throw new ScanNotConfirmable($e->getMessage().' Scan the pass again.');
        }

        $this->syncVisitor($pass, $decision, $gate);
        $this->log($pass, $gate, $guard, $decision->value, $pending['method']);

        return [
            'action' => $decision->value,
            'passStatus' => $pass->status->value,
            'time' => $now->format('g:i A'),
            'message' => ($decision === ScanDecision::CheckIn ? 'Checked in at ' : 'Checked out at ').$now->format('g:i A'),
        ];
    }

    /**
     * Checks a pass holder in without a code, for a visitor at the gate with a
     * flat phone. The same pass rules as a scan apply: state, validity window
     * and blocklist. Only the token checks are skipped, because there is no
     * token.
     *
     * @throws ScanNotConfirmable
     */
    public function manualCheckIn(GatePass $pass, User $guard, GateId $gate): void
    {
        $blocked = $pass->visitor && ($pass->visitor->is_blocked || BlocklistEntry::blocks($pass->holder_name));

        $canEnter = match ($pass->status) {
            PassStatus::Issued, PassStatus::Active => true,
            PassStatus::CheckedOut => ! $pass->single_entry,
            default => false,
        };

        $reason = match (true) {
            $pass->isRevoked() => 'The pass has been revoked.',
            $blocked => 'The holder is on the community blocklist.',
            ! $canEnter => "The pass is {$pass->status->label()}.",
            ! $pass->isWithinValidity(now()) => 'The pass is outside its validity window.',
            default => null,
        };

        if ($reason !== null) {
            $this->log($pass, $gate, $guard, ValidationStatus::Deny->value, 'Manual (no code)', "MANUAL_CHECK_IN_REFUSED: {$reason}");

            throw new ScanNotConfirmable($reason);
        }

        if ($pass->status === PassStatus::Issued) {
            $pass->transitionTo(PassStatus::Active, $guard, 'First use at the gate', $gate);
        }

        $pass->transitionTo(PassStatus::CheckedIn, $guard, 'Checked in without a code', $gate);
        $this->syncVisitor($pass, ScanDecision::CheckIn, $gate);
        $this->log($pass, $gate, $guard, ScanDecision::CheckIn->value, 'Manual (no code)');
    }

    /** @throws ScanNotConfirmable */
    public function manualCheckOut(GatePass $pass, User $guard, GateId $gate): void
    {
        if (! $pass->canTransitionTo(PassStatus::CheckedOut)) {
            throw new ScanNotConfirmable("The pass is {$pass->status->label()}, not checked in.");
        }

        $pass->transitionTo(PassStatus::CheckedOut, $guard, 'Checked out without a code', $gate);
        $this->syncVisitor($pass, ScanDecision::CheckOut, $gate);
        $this->log($pass, $gate, $guard, ScanDecision::CheckOut->value, 'Manual (no code)');
    }

    /** Keeps the visitor record, which the Visitors page reads, in step with its pass. */
    private function syncVisitor(GatePass $pass, ScanDecision $decision, GateId $gate): void
    {
        $visitor = $pass->visitor;

        if (! $visitor) {
            return;
        }

        if ($decision === ScanDecision::CheckIn) {
            $visitor->checkIn();
            event(new VisitorCheckedInEvent($visitor, $this->gateName($gate)));
        } else {
            $visitor->checkOut();
        }
    }

    private function log(GatePass $pass, GateId $gate, User $guard, string $result, string $method, ?string $denyReason = null): void
    {
        AccessLogEntry::create([
            'user_id' => $pass->user_id ?? $pass->visitor?->homeowner_id,
            'user_name' => $pass->holder_name,
            'user_role' => $pass->user?->role->value ?? $pass->category->value,
            'method' => $method,
            'gate' => $this->gateName($gate),
            'pass_id' => $pass->pass_id,
            'result' => $result,
            'deny_reason' => $denyReason,
            'scanned_by' => $guard->id,
            'occurred_at' => now(),
        ]);
    }

    private function gateName(GateId $gate): string
    {
        return config('gatepass.gates.'.$gate->value, $gate->value);
    }

    private function cacheKey(string $scanId): string
    {
        return "gate-scan:{$scanId}";
    }
}
