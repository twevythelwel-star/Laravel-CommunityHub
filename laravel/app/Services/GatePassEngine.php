<?php

namespace App\Services;

use App\Enums\DenyReason;
use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\ScanDecision;
use App\Enums\ValidationStatus;
use App\Models\BlocklistEntry;
use App\Models\GatePass;
use App\Models\GatePassNonce;
use App\Models\User;
use App\Models\Visitor;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Digital Gate Pass Engine.
 *
 *   [Scan] -> [1 STRUCTURE] -> [2 SIGNATURE] -> [3 REGISTRY] -> [4 POLICY] -> CHECK_IN | CHECK_OUT | REJECT
 *
 * A token proves two things: that this server signed it (HMAC-SHA256 with a
 * server-only key), and that it was minted in the current time slot (a
 * nonce that is spent on first scan). Everything else is decided by the pass
 * registry, not by the token. Every claim the token carries (profile, person,
 * property, zone, gate, rotation) must agree with the registry row, and the
 * pass's own state and validity window decide whether it can be used at all.
 *
 * Shape and colour are the pass's visual identity, for the guard's eyes.
 * They never authorize anything.
 */
class GatePassEngine
{
    public function __construct(
        private readonly ?string $secret = null,
    ) {
        if (blank($this->secret ?? config('gatepass.secret'))) {
            throw new RuntimeException(
                'GATE_ENGINE_SECRET is not set. Generate one with: php artisan gatepass:secret'
            );
        }
    }

    private function key(): string
    {
        return $this->secret ?? config('gatepass.secret');
    }

    // ─────────────────────────────────────────────────────────────────
    // Issuance
    // ─────────────────────────────────────────────────────────────────

    /**
     * Mints a dynamic, signed token for a pass.
     *
     * The validity window is aligned to a rolling slot (default 30s) so the same
     * pass yields the same token within a slot and a screenshot dies with it.
     * `$gate` narrows an any-gate pass to one gate; it cannot widen a pass.
     *
     * @return array{token: string, payload: array<string, mixed>, valid_from: CarbonImmutable, valid_until: CarbonImmutable}
     */
    public function issueToken(GatePass $pass, ?GateId $gate = null, ?int $windowSeconds = null): array
    {
        $window = $windowSeconds ?? (int) config('gatepass.window_seconds', 30);
        $nowSec = CarbonImmutable::now()->getTimestamp();

        $windowIndex = intdiv($nowSec, $window);
        $validFrom = $windowIndex * $window;
        $validUntil = $validFrom + $window;

        $uidTail = substr((string) ($pass->user?->uid ?? $pass->pass_id), -4);
        $nonce = sprintf(
            'NONCE-%s-%s%s',
            $uidTail,
            strtoupper(dechex($windowIndex)),
            $pass->rotation_seq > 1 ? '-S'.$pass->rotation_seq : ''
        );

        $payload = [
            'gpe' => config('gatepass.protocol.engine_id', 'GPE'),
            'cid' => config('gatepass.default_community_id'),
            'v' => (int) config('gatepass.protocol.version', 1),
            'pid' => $pass->pass_id,
            'cat' => $pass->category->value,
            'zone' => $this->zoneOf($pass),
            'uid' => (string) ($pass->user?->uid ?? ''),
            'nam' => $pass->holder_name,
            'prop' => $pass->property ?? 'Unassigned',
            'gate' => ($gate ?? $pass->designated_gate ?? GateId::Any)->value,
            'vf' => $validFrom,
            'vu' => $validUntil,
            'nonce' => $nonce,
            't' => $nowSec,
            'cvar' => $this->variantFor($pass)['id'],
            'seq' => $pass->rotation_seq,
        ];

        $signature = $this->sign($payload);
        $payload['sig'] = $signature;
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'token' => config('gatepass.protocol.envelope_prefix').$encodedPayload.'.'.$signature,
            'payload' => $payload,
            'valid_from' => CarbonImmutable::createFromTimestamp($validFrom),
            'valid_until' => CarbonImmutable::createFromTimestamp($validUntil),
        ];
    }

    /**
     * The account's pass, issued on first use.
     *
     * Returns the latest pass whatever its state. It used to create a new pass
     * whenever there was no *active* one, so a pass revoked by security came
     * back, freshly issued, the next time its holder opened the page. A
     * revoked or suspended pass now stays that way until security reissues it
     * (reissuePassFor()).
     *
     * Account passes are auto-approved: the account itself is the approval, so
     * the pass is created ACTIVE, multi-entry, with no end date, and a random
     * approved colour.
     */
    public function issuePassFor(User $user): GatePass
    {
        return $user->gatePasses()->whereNull('visitor_id')->latest('id')->first()
            ?? $this->createAccountPass($user);
    }

    /**
     * A new pass for an account whose last one was revoked or expired. The old
     * pass keeps its ID and history; the new one gets the next ID.
     */
    public function reissuePassFor(User $user, User $by): GatePass
    {
        $current = $user->gatePasses()->whereNull('visitor_id')->latest('id')->first();

        if ($current && ! $current->status->isTerminal()) {
            throw new RuntimeException("Pass {$current->pass_id} is still {$current->status->label()}; revoke it before reissuing.");
        }

        return $this->createAccountPass($user, $by, 'Reissued by '.$by->display_name);
    }

    private function createAccountPass(User $user, ?User $by = null, string $reason = 'Issued with the account'): GatePass
    {
        $category = $user->passCategory();

        $pass = GatePass::create([
            'pass_id' => $this->uniquePassId($category, (string) $user->id),
            'user_id' => $user->id,
            'category' => $category,
            'holder_name' => $user->display_name,
            'property' => $user->propertyLabel(),
            'access_zone' => $category->defaultZone(),
            'designated_gate' => GateId::Any,
            'single_entry' => false,
            'color_variant' => $this->randomApprovedColor($category)['id'],
            'rotation_seq' => 1,
            'status' => PassStatus::Active,
            'status_changed_at' => now(),
        ]);

        $pass->recordCreation($by, $reason);

        return $pass;
    }

    /**
     * Requests a pass for a visitor or contractor registered by a resident.
     *
     * A resident's own guest is approved and issued straight away: registering
     * the guest is the resident's approval. A contractor stays REQUESTED until
     * security or an administrator approves it (see approve()).
     */
    public function issueGuestPass(Visitor $visitor, User $host, PassCategory $category = PassCategory::Visitor): GatePass
    {
        if (! $category->isGuest()) {
            throw new RuntimeException("{$category->value} is not a guest pass category.");
        }

        [$validFrom, $validUntil] = $this->guestWindow($visitor);

        return DB::transaction(function () use ($visitor, $host, $category, $validFrom, $validUntil) {
            $pass = GatePass::create([
                'pass_id' => $this->uniquePassId($category, (string) $visitor->id),
                'visitor_id' => $visitor->id,
                'category' => $category,
                'holder_name' => $visitor->name,
                'property' => $host->propertyLabel(),
                'access_zone' => $category->defaultZone(),
                'designated_gate' => GateId::Any,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'single_entry' => $visitor->type !== 'Recurring',
                'color_variant' => $this->randomApprovedColor($category)['id'],
                'rotation_seq' => 1,
                'status' => PassStatus::Requested,
                'status_changed_at' => now(),
            ]);

            $pass->recordCreation($host, "Requested by {$host->display_name}");

            if (! $category->requiresApproval()) {
                $this->approve($pass, $host, 'Registered by the host resident');
            }

            return $pass;
        });
    }

    /** Approves a requested pass and issues it in one step. */
    public function approve(GatePass $pass, ?User $by = null, ?string $reason = null): GatePass
    {
        $pass->transitionTo(PassStatus::Approved, $by, $reason);

        return $pass->transitionTo(PassStatus::Issued, $by, 'Issued on approval');
    }

    /**
     * Brings a guest pass's window in line with its visitor record after the
     * host edits the arrival time or visit type. Only before the visit starts:
     * once someone is inside, the record of their stay is left alone.
     */
    public function syncGuestPass(GatePass $pass, Visitor $visitor): void
    {
        if (! in_array($pass->status, [PassStatus::Requested, PassStatus::Approved, PassStatus::Issued, PassStatus::Active], true)) {
            return;
        }

        [$validFrom, $validUntil] = $this->guestWindow($visitor);

        $pass->update([
            'holder_name' => $visitor->name,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'single_entry' => $visitor->type !== 'Recurring',
        ]);
    }

    /**
     * The window a guest pass is valid for, from the visitor's expected
     * arrival and visit type. See config/gatepass.php `guest_windows`.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function guestWindow(Visitor $visitor): array
    {
        $arrival = CarbonImmutable::parse($visitor->expected_at);
        $windows = config('gatepass.guest_windows');

        $from = $arrival->subMinutes((int) $windows['opens_minutes_before']);
        $until = $visitor->type === 'Recurring'
            ? $arrival->addDays((int) $windows['recurring_days'])
            : $arrival->addHours((int) $windows['one_time_hours']);

        return [$from, $until];
    }

    /**
     * Expires every pass whose window has closed. Called by the scheduler.
     * Someone still CHECKED_IN is not expired: they have to be checked out.
     */
    public function expireLapsedPasses(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $expired = 0;

        GatePass::query()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', $now)
            ->whereIn('status', array_map(fn (PassStatus $s) => $s->value, [
                PassStatus::Requested, PassStatus::Approved, PassStatus::Issued,
                PassStatus::Active, PassStatus::CheckedOut, PassStatus::Suspended,
            ]))
            ->each(function (GatePass $pass) use (&$expired) {
                $pass->transitionTo(PassStatus::Expired, reason: 'Validity window closed');
                $expired++;
            });

        return $expired;
    }

    // ─────────────────────────────────────────────────────────────────
    // Validation pipeline
    // ─────────────────────────────────────────────────────────────────

    /**
     * Runs the full pipeline and returns a report. `decision` is what the
     * guard is told to do; `status` stays ALLOW/DENY for the access log and
     * older clients.
     *
     * @return array<string, mixed>
     */
    public function validate(
        string $rawQr,
        ?GateId $currentGate = null,
        ?CarbonImmutable $now = null,
        bool $bypassReplayCheck = false,
    ): array {
        $now = $now ?? CarbonImmutable::now();
        $nowSec = $now->getTimestamp();
        $currentGate = $currentGate ?? GateId::Gate01;

        // ── STAGE 1: structure & engine recognition ──
        $accepted = (array) config('gatepass.protocol.accepted_prefixes');
        $recognised = collect($accepted)->contains(fn ($prefix) => str_starts_with($rawQr, $prefix));

        if (! $recognised) {
            return $this->denyReport($now, $currentGate, DenyReason::NotAGpeGatePass,
                'Unrecognized QR token format. Not issued by this Digital Gate Pass Engine.',
                'STRUCTURE', 'Malformed QR envelope format.');
        }

        $parts = explode('.', $rawQr);
        if (count($parts) !== 3) {
            return $this->denyReport($now, $currentGate, DenyReason::InvalidQrStructure,
                'Token envelope requires exactly 3 segments (protocol header, payload, signature).',
                'STRUCTURE', 'Segment count mismatch.');
        }

        $decoded = $this->base64UrlDecode($parts[1]);
        $payload = $decoded === null ? null : json_decode($decoded, true);

        if (! is_array($payload) || ! isset($payload['pid'], $payload['cat'], $payload['sig'], $payload['vf'], $payload['vu'], $payload['nonce'])) {
            return $this->denyReport($now, $currentGate, DenyReason::InvalidQrStructure,
                'Payload could not be decoded or is missing required claims.',
                'STRUCTURE', 'Payload decode failure.');
        }

        if (($payload['gpe'] ?? null) !== config('gatepass.protocol.engine_id')) {
            return $this->denyReport($now, $currentGate, DenyReason::NotAGpeGatePass,
                'Payload does not carry the Gate Pass Engine identifier.',
                'STRUCTURE', 'Engine identifier missing.');
        }

        if (($payload['cid'] ?? null) !== config('gatepass.default_community_id')) {
            return $this->denyReport($now, $currentGate, DenyReason::UnauthorizedCommunity,
                sprintf('Pass was issued for community %s, not this estate.', $payload['cid'] ?? 'UNKNOWN'),
                'STRUCTURE', 'Community identifier mismatch.', $payload);
        }

        $category = PassCategory::tryFrom((string) $payload['cat']) ?? PassCategory::Homeowner;
        $policy = $this->policyFor($category);

        $report = $this->baseReport($payload, $category, $policy, $now, $currentGate);
        $report['stages']['structure'] = $this->stage(true, 'STRUCTURE', 'Envelope recognised; payload decoded and claims present.', $now);
        $report['checks']['payloadAuthenticity'] = true;
        $report['checks']['communityMatch'] = true;

        // ── STAGE 2: cryptographic signature ──
        $claimed = (string) $payload['sig'];
        $unsigned = $payload;
        unset($unsigned['sig']);

        // The envelope carries the signature twice, inside the payload and as
        // the third segment. Both must be the real one; the trailing copy used
        // to be ignored, so it could be anything.
        if (! hash_equals($this->sign($unsigned), $claimed) || ! hash_equals($claimed, $parts[2])) {
            $report['stages']['cryptography'] = $this->stage(false, 'SIGNATURE', 'HMAC-SHA256 verification failed.', $now);

            return $this->reject($report, DenyReason::SignatureMismatch, 'Payload signature does not match. Token was altered or forged.');
        }

        $report['stages']['cryptography'] = $this->stage(true, 'SIGNATURE', 'HMAC-SHA256 signature verified against server key.', $now);
        $report['checks']['cryptographicSignature'] = true;

        // ── STAGE 3: token window, registry, state, replay ──
        if ($nowSec > (int) $payload['vu']) {
            return $this->rejectAt($report, 'serverCache', DenyReason::TokenExpired,
                sprintf('Dynamic credential expired %d seconds ago.', $nowSec - (int) $payload['vu']),
                sprintf('Token valid window was %s - %s. Current time is %s.',
                    CarbonImmutable::createFromTimestamp((int) $payload['vf'])->toTimeString(),
                    CarbonImmutable::createFromTimestamp((int) $payload['vu'])->toTimeString(),
                    $now->toTimeString()), $now);
        }

        if ($nowSec < (int) $payload['vf'] - (int) config('gatepass.clock_skew_seconds', 5)) {
            return $this->rejectAt($report, 'serverCache', DenyReason::TokenNotYetValid,
                'Credential timestamp is in the future.', 'Token has not yet reached its active validity window.', $now);
        }

        $report['checks']['temporalTimeAuth'] = true;

        // Pass ID: the pass must exist. A signed token for a pass that is no
        // longer on the registry is not a credential.
        $pass = GatePass::with(['user', 'visitor'])->where('pass_id', $payload['pid'])->first();

        if (! $pass) {
            return $this->rejectAt($report, 'serverCache', DenyReason::UnknownPass,
                sprintf('Pass %s is not on the registry.', $payload['pid']), 'Pass ID lookup failed.', $now);
        }

        $report['checks']['passRegistered'] = true;
        $report['passStatus'] = $pass->status->value;
        $report['holderType'] = $pass->visitor_id ? 'VISITOR' : 'ACCOUNT';
        $report['visitorId'] = $pass->visitor_id;

        // Rotation: a token minted before the pass rotated is superseded.
        if ((int) ($payload['seq'] ?? 0) !== $pass->rotation_seq) {
            return $this->rejectAt($report, 'serverCache', DenyReason::PassSuperseded,
                'The pass has been rotated since this code was generated.',
                sprintf('Token sequence %d, current sequence %d.', (int) ($payload['seq'] ?? 0), $pass->rotation_seq), $now);
        }

        // Profile, person, property, zone, gate: the token must agree with the registry.
        $mismatch = $this->claimMismatch($payload, $pass);
        if ($mismatch !== null) {
            return $this->rejectAt($report, 'serverCache', DenyReason::ClaimMismatch,
                "The code's {$mismatch} does not match the pass registry.",
                "Registry disagreement on {$mismatch}.", $now);
        }

        $report['checks']['profileMatch'] = true;
        $report['checks']['personMatch'] = true;
        $report['checks']['propertyMatch'] = true;
        $report['checks']['shapeCategoryMatch'] = true;
        $report['checks']['colorClassMatch'] = true;

        // Revocation and current status.
        if ($pass->isRevoked()) {
            return $this->rejectAt($report, 'serverCache', DenyReason::PassRevoked,
                'Pass ID has been revoked in the central security directory.',
                sprintf('Pass %s was revoked on %s. %s', $pass->pass_id, $pass->revoked_at?->toDayDateTimeString(), $pass->revocation_reason ?? ''), $now);
        }

        $report['checks']['notRevoked'] = true;

        $isExit = $pass->status === PassStatus::CheckedIn;

        if (! $isExit && ! $this->canEnter($pass)) {
            return $this->rejectAt($report, 'serverCache', DenyReason::PassNotUsable,
                sprintf('Pass is %s.', $pass->single_entry && $pass->status === PassStatus::CheckedOut
                    ? 'single-entry and has already been used'
                    : $pass->status->label()),
                sprintf('Status %s cannot be admitted.', $pass->status->value), $now);
        }

        $report['checks']['statusEligible'] = true;

        // Start/end time: entry only inside the pass's own window. Leaving is
        // always allowed; someone who overstays still has to be let out.
        if (! $isExit && ! $pass->isWithinValidity($now)) {
            return $this->rejectAt($report, 'serverCache', DenyReason::OutsideValidity,
                sprintf('Pass is valid %s to %s.',
                    $pass->valid_from?->toDayDateTimeString() ?? 'now',
                    $pass->valid_until?->toDayDateTimeString() ?? 'open-ended'),
                'Outside the pass validity window.', $now);
        }

        $report['checks']['validityPeriod'] = true;

        // Person: the holder must still be allowed in.
        if ($pass->user && ! $pass->user->isActive()) {
            return $this->rejectAt($report, 'serverCache', DenyReason::UserNotActive,
                'Pass holder account is deactivated.', 'Holder account is not active.', $now);
        }

        if (! $isExit && $pass->visitor && ($pass->visitor->is_blocked || BlocklistEntry::blocks($pass->holder_name))) {
            return $this->rejectAt($report, 'serverCache', DenyReason::HolderBlocked,
                'Pass holder is on the community blocklist.', 'Blocklist match.', $now);
        }

        $report['checks']['personVerified'] = true;

        // Replay: the first scan of a nonce wins. The insert is the lock.
        if (! $bypassReplayCheck) {
            $firstSeen = $this->claimNonce((string) $payload['nonce'], (string) $payload['pid'], (int) $payload['vu']);

            if ($firstSeen !== null) {
                return $this->rejectAt($report, 'serverCache', DenyReason::ReplayAttack,
                    'Static screenshot duplicate detected. Nonce has already been checked.',
                    sprintf('Nonce %s was already used at %s.', $payload['nonce'], $firstSeen->toTimeString()), $now);
            }
        }

        $report['checks']['replayFree'] = true;
        $report['stages']['serverCache'] = $this->stage(true, 'SERVER_CACHE',
            sprintf('Pass %s is %s; claims match the registry; nonce %s registered.', $pass->pass_id, $pass->status->label(), $payload['nonce']), $now);

        // ── STAGE 4: access policy (entry only) ──
        if (! $isExit) {
            $gateRule = $this->gateRuleFailure($pass, $payload, $policy, $currentGate);
            if ($gateRule !== null) {
                [$reason, $detail, $stageMessage] = $gateRule;

                return $this->rejectAt($report, 'accessPolicy', $reason, $detail, $stageMessage, $now);
            }

            $report['checks']['physicalGateAuth'] = true;
            $report['checks']['zoneAuth'] = true;

            $hoursFailure = $this->hoursFailure($policy, $category, $now);
            if ($hoursFailure !== null) {
                return $this->rejectAt($report, 'accessPolicy', DenyReason::OutsideHours, $hoursFailure[0], $hoursFailure[1], $now);
            }
        } else {
            $report['checks']['physicalGateAuth'] = true;
            $report['checks']['zoneAuth'] = true;
        }

        // ── Cleared ──
        $decision = $isExit ? ScanDecision::CheckOut : ScanDecision::CheckIn;

        $report['status'] = ValidationStatus::Allow->value;
        $report['decision'] = $decision->value;
        $report['primaryReason'] = $isExit
            ? 'CHECK_OUT: Holder is recorded inside; confirm departure.'
            : 'CHECK_IN: Signature, registry, validity and policy checks all passed.';
        $report['stages']['accessPolicy'] = $this->stage(true, 'ACCESS_POLICY', $isExit
            ? 'Departure: gate, zone and hours are not applied to someone leaving.'
            : sprintf('Policy clearance active. Authorized for: %s...', implode(', ', array_slice($policy['authorized_zones'], 0, 2))), $now);

        $variant = $this->variantFor($pass);
        $report['visualIdentity'] = [
            'shape' => $category->shape()->value,
            'colorVariantId' => $variant['id'],
            'colorVariantName' => $variant['name'],
            'colorHex' => $variant['hex'],
            'isApprovedPalette' => true,
            'contrastRatio' => $variant['contrast_ratio'],
            'wcagPass' => $variant['wcag_pass'],
            'rotationSequence' => $pass->rotation_seq,
            'securityNote' => 'Visual identifier only; access is authorized by the signed token and the pass registry.',
        ];

        return $report;
    }

    /** ISSUED, ACTIVE, or CHECKED_OUT on a pass that allows re-entry. */
    private function canEnter(GatePass $pass): bool
    {
        return match ($pass->status) {
            PassStatus::Issued, PassStatus::Active => true,
            PassStatus::CheckedOut => ! $pass->single_entry,
            default => false,
        };
    }

    /**
     * The first token claim that disagrees with the registry row, or null.
     *
     * @param  array<string, mixed>  $payload
     */
    private function claimMismatch(array $payload, GatePass $pass): ?string
    {
        $checks = [
            'profile' => [$payload['cat'] ?? null, $pass->category->value],
            'holder name' => [$payload['nam'] ?? null, $pass->holder_name],
            'holder account' => [(string) ($payload['uid'] ?? ''), (string) ($pass->user?->uid ?? '')],
            'property' => [$payload['prop'] ?? null, $pass->property ?? 'Unassigned'],
            'zone' => [$payload['zone'] ?? null, $this->zoneOf($pass)],
        ];

        foreach ($checks as $field => [$claimed, $registered]) {
            if ($claimed !== $registered) {
                return $field;
            }
        }

        // A token may narrow an any-gate pass to one gate, never the reverse.
        $registryGate = $pass->designated_gate ?? GateId::Any;
        if ($registryGate !== GateId::Any && ($payload['gate'] ?? null) !== $registryGate->value) {
            return 'gate';
        }

        return null;
    }

    /**
     * Gate and zone rules for entry, or null when they pass.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $policy
     * @return array{0: DenyReason, 1: string, 2: string}|null
     */
    private function gateRuleFailure(GatePass $pass, array $payload, array $policy, GateId $currentGate): ?array
    {
        $tokenGate = GateId::tryFrom((string) ($payload['gate'] ?? GateId::Any->value)) ?? GateId::Any;

        if ($tokenGate !== GateId::Any && $tokenGate !== $currentGate) {
            return [DenyReason::UnauthorizedGate,
                sprintf('Pass restricted strictly to %s. Attempted ingress at %s.', $tokenGate->value, $currentGate->value),
                sprintf('Physical boundary enforcement: designated gate is %s.', $tokenGate->value)];
        }

        if (! in_array($currentGate->value, $policy['allowed_gates'], true)) {
            return [DenyReason::UnauthorizedGate,
                sprintf('Category %s is not cleared for %s.', $pass->category->value, $currentGate->value),
                'Category gate clearance does not include this portal.'];
        }

        $zone = $this->zoneOf($pass);
        $served = config('gatepass.gate_zones.'.$currentGate->value);

        if (is_array($served) && ! in_array($zone, $served, true)) {
            return [DenyReason::UnauthorizedZone,
                sprintf('%s does not admit into %s.', config('gatepass.gates.'.$currentGate->value, $currentGate->value), $zone),
                'Zone not served by this gate.'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array{0: string, 1: string}|null
     */
    private function hoursFailure(array $policy, PassCategory $category, CarbonImmutable $now): ?array
    {
        $hours = $policy['operational_hours'];

        if ($hours['is_24_hours'] ?? false) {
            return null;
        }

        // Carbon dayOfWeek matches the JS getDay() convention: 0 = Sunday.
        if (isset($hours['days_of_week']) && ! in_array((int) $now->dayOfWeek, $hours['days_of_week'], true)) {
            return [sprintf('Ingress not permitted on %ss.', $now->format('l')),
                sprintf('Category %s schedule restricted to operating days.', $category->value)];
        }

        $hour = (int) $now->hour;
        if (isset($hours['start_hour'], $hours['end_hour']) && ($hour < $hours['start_hour'] || $hour >= $hours['end_hour'])) {
            return [sprintf('Operational shift bounds are %d:00 - %d:00. Current time: %s.', $hours['start_hour'], $hours['end_hour'], $now->toTimeString()),
                sprintf('Shift schedule violation: access prohibited outside %d:00 to %d:00.', $hours['start_hour'], $hours['end_hour'])];
        }

        return null;
    }

    /**
     * Records a nonce as used. Returns null if this is the first sighting, or
     * the original timestamp when it has been seen before.
     */
    private function claimNonce(string $nonce, string $passId, int $expiresAtTs): ?CarbonImmutable
    {
        try {
            GatePassNonce::create([
                'nonce' => $nonce,
                'pass_id' => $passId,
                'first_seen_at' => now(),
                // Keep the row a little past the window so late duplicates are caught.
                'expires_at' => CarbonImmutable::createFromTimestamp($expiresAtTs)->addMinutes(5),
            ]);

            return null;
        } catch (UniqueConstraintViolationException) {
            $existing = GatePassNonce::where('nonce', $nonce)->first();

            return $existing
                ? CarbonImmutable::parse($existing->first_seen_at)
                : CarbonImmutable::now();
        }
    }

    /** Drops nonce rows whose window has closed. Called by the scheduler. */
    public function pruneNonces(): int
    {
        return GatePassNonce::expired()->delete();
    }

    // ─────────────────────────────────────────────────────────────────
    // Visual identity
    // ─────────────────────────────────────────────────────────────────

    /**
     * The colour a pass carries. Chosen at random from the approved palette at
     * issue and stored on the pass. Older rows stored the colour's name, and
     * rows with neither fall back to the deterministic assignment.
     *
     * @return array{id: string, name: string, hex: string, accent_hex: string, contrast_ratio: float, wcag_pass: bool}
     */
    public function variantFor(GatePass $pass): array
    {
        $palette = config('gatepass.palettes.'.$pass->category->value) ?? [];

        foreach ($palette as $variant) {
            if ($pass->color_variant !== null && ($variant['id'] === $pass->color_variant || $variant['name'] === $pass->color_variant)) {
                return $variant;
            }
        }

        return $this->assignedColorVariant($pass->category, $pass->pass_id, $pass->rotation_seq);
    }

    /**
     * Deterministic colour assignment: the same seed and sequence always give
     * the same variant. The fallback for passes issued before colours were
     * stored.
     *
     * @return array{id: string, name: string, hex: string, accent_hex: string, contrast_ratio: float, wcag_pass: bool}
     */
    public function assignedColorVariant(PassCategory $category, ?string $seed = null, int $rotationSeq = 0): array
    {
        $palette = config('gatepass.palettes.'.$category->value)
            ?? config('gatepass.palettes.HOMEOWNER');

        if (empty($palette)) {
            $cfg = config('gatepass.categories.'.$category->value);

            return [
                'id' => 'default',
                'name' => 'Default Class Theme',
                'hex' => $cfg['theme_color'],
                'accent_hex' => $cfg['accent_color'],
                'contrast_ratio' => 6.5,
                'wcag_pass' => true,
            ];
        }

        $seedStr = $seed ?: 'default-seed';
        $seedNum = 0;
        foreach (str_split($seedStr) as $char) {
            $seedNum = ($seedNum * 31 + ord($char)) & 0xFFFFFFFF;
        }

        return $palette[($seedNum + $rotationSeq) % count($palette)];
    }

    /**
     * Picks a random approved variant, optionally excluding ids already in use.
     *
     * @param  list<string>  $excludeColorIds
     * @return array{id: string, name: string, hex: string, accent_hex: string, contrast_ratio: float, wcag_pass: bool}
     */
    public function randomApprovedColor(PassCategory $category, array $excludeColorIds = []): array
    {
        $palette = config('gatepass.palettes.'.$category->value) ?? [];

        $candidates = array_values(array_filter(
            $palette,
            fn (array $c) => ! in_array($c['id'], $excludeColorIds, true),
        ));

        if ($candidates === []) {
            $candidates = $palette;
        }

        return $candidates[random_int(0, count($candidates) - 1)];
    }

    /**
     * Gives a pass a new random approved colour and advances its sequence.
     * Tokens carry the sequence, so every code minted before the rotation is
     * rejected as superseded.
     *
     * @return array{next_seq: int, variant: array<string, mixed>, rotated_at: mixed}
     */
    public function rotateVisualIdentity(GatePass $pass): array
    {
        $variant = $this->randomApprovedColor($pass->category, [$this->variantFor($pass)['id']]);
        $nextSeq = $pass->rotation_seq + 1;

        $pass->update([
            'rotation_seq' => $nextSeq,
            'color_variant' => $variant['id'],
            'last_rotated_at' => now(),
        ]);

        return [
            'next_seq' => $nextSeq,
            'variant' => $variant,
            'rotated_at' => $pass->last_rotated_at,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    /** Standard pass ID formatter, e.g. GP-HO-0042. */
    public function formatPassId(PassCategory $category, string $rawId): string
    {
        $digits = preg_replace('/\D/', '', $rawId) ?: '0';

        return sprintf('%s-%04d', $category->passIdPrefix(), (int) $digits);
    }

    /**
     * A pass ID not yet on the registry. A re-issued pass (after revocation,
     * or a second visit) gets a suffix instead of colliding with the old one.
     */
    private function uniquePassId(PassCategory $category, string $rawId): string
    {
        $base = $this->formatPassId($category, $rawId);
        $candidate = $base;

        for ($n = 2; GatePass::where('pass_id', $candidate)->exists(); $n++) {
            $candidate = "{$base}-{$n}";
        }

        return $candidate;
    }

    private function zoneOf(GatePass $pass): string
    {
        return $pass->access_zone ?: $pass->category->defaultZone();
    }

    /** @return array<string, mixed> */
    public function policyFor(PassCategory $category): array
    {
        return config('gatepass.policies.'.$category->value)
            ?? config('gatepass.policies.HOMEOWNER');
    }

    /** @return array<string, mixed> */
    public function categoryConfig(PassCategory $category): array
    {
        return config('gatepass.categories.'.$category->value)
            ?? config('gatepass.categories.HOMEOWNER');
    }

    /**
     * Real HMAC-SHA256 over a canonical (key-sorted) JSON encoding, so the
     * signature is stable regardless of key order.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sign(array $payload): string
    {
        return hash_hmac('sha256', $this->canonicalJson($payload), $this->key());
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->canonicalise($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonicalise($item), $value);
        }

        ksort($value);

        return array_map(fn ($item) => $this->canonicalise($item), $value);
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $encoded): ?string
    {
        $base64 = strtr($encoded, '-_', '+/');
        $padded = str_pad($base64, (int) (ceil(strlen($base64) / 4) * 4), '=');
        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }

    /** @return array{passed: bool, stage: string, message: string, timestamp: string} */
    private function stage(bool $passed, string $stage, string $message, CarbonImmutable $now): array
    {
        return [
            'passed' => $passed,
            'stage' => $stage,
            'message' => $message,
            'timestamp' => $now->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function reject(array $report, DenyReason $reason, string $detail): array
    {
        $report['status'] = ValidationStatus::Deny->value;
        $report['decision'] = ScanDecision::Reject->value;
        $report['denyReason'] = $reason->value;
        $report['primaryReason'] = $reason->value.': '.$detail;

        return $report;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function rejectAt(array $report, string $slot, DenyReason $reason, string $detail, string $stageMessage, CarbonImmutable $now): array
    {
        $name = $slot === 'accessPolicy' ? 'ACCESS_POLICY' : 'SERVER_CACHE';
        $report['stages'][$slot] = $this->stage(false, $name, $stageMessage, $now);

        return $this->reject($report, $reason, $detail);
    }

    /**
     * Report skeleton for a decoded, recognised payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function baseReport(array $payload, PassCategory $category, array $policy, CarbonImmutable $now, GateId $gate): array
    {
        $issuedAt = CarbonImmutable::createFromTimestamp((int) ($payload['t'] ?? $now->getTimestamp()));
        $expiresAt = CarbonImmutable::createFromTimestamp((int) ($payload['vu'] ?? $now->getTimestamp()));

        return [
            'status' => ValidationStatus::Deny->value,
            'decision' => ScanDecision::Reject->value,
            'denyReason' => null,
            'primaryReason' => '',
            'category' => $category->value,
            'shape' => $category->shape()->value,
            'communityId' => $payload['cid'] ?? config('gatepass.default_community_id'),
            'accessZone' => $payload['zone'] ?? 'ZONE-UNKNOWN',
            'passId' => $payload['pid'] ?? 'UNKNOWN',
            'passStatus' => null,
            'holderType' => null,
            'visitorId' => null,
            'property' => $payload['prop'] ?? 'UNKNOWN',
            'userName' => $payload['nam'] ?? 'Unknown Visitor',
            'gateChecked' => $gate->value,
            'stages' => [
                'structure' => $this->stage(false, 'STRUCTURE', 'Not evaluated.', $now),
                'cryptography' => $this->stage(false, 'SIGNATURE', 'Signature could not be evaluated.', $now),
                'serverCache' => $this->stage(false, 'SERVER_CACHE', 'Server lookup blocked.', $now),
                'accessPolicy' => $this->stage(false, 'ACCESS_POLICY', 'Policy evaluation blocked.', $now),
            ],
            'checks' => [
                'payloadAuthenticity' => false,
                'communityMatch' => false,
                'cryptographicSignature' => false,
                'temporalTimeAuth' => false,
                'passRegistered' => false,
                'profileMatch' => false,
                'personMatch' => false,
                'propertyMatch' => false,
                'notRevoked' => false,
                'statusEligible' => false,
                'validityPeriod' => false,
                'personVerified' => false,
                'replayFree' => false,
                'physicalGateAuth' => false,
                'zoneAuth' => false,
                'shapeCategoryMatch' => false,
                'colorClassMatch' => false,
            ],
            'policy' => $policy,
            'issuedAt' => $issuedAt->toIso8601String(),
            'expiresAt' => $expiresAt->toIso8601String(),
            'secondsRemaining' => max(0, $expiresAt->getTimestamp() - $now->getTimestamp()),
        ];
    }

    /**
     * Terminal REJECT report for a token that never got far enough to decode.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function denyReport(
        CarbonImmutable $now,
        GateId $gate,
        DenyReason $reason,
        string $detail,
        string $stage,
        string $stageMessage,
        array $payload = [],
    ): array {
        $category = PassCategory::tryFrom($payload['cat'] ?? '') ?? PassCategory::Homeowner;
        $report = $this->baseReport($payload, $category, $this->policyFor($category), $now, $gate);

        $slot = match ($stage) {
            'STRUCTURE' => 'structure',
            'SIGNATURE' => 'cryptography',
            'SERVER_CACHE' => 'serverCache',
            default => 'accessPolicy',
        };
        $report['stages'][$slot] = $this->stage(false, $stage, $stageMessage, $now);

        return $this->reject($report, $reason, $detail);
    }
}
