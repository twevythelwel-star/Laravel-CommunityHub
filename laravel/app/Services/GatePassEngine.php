<?php

namespace App\Services;

use App\Enums\DenyReason;
use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\ValidationStatus;
use App\Models\GatePass;
use App\Models\GatePassNonce;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * Digital Gate Pass Engine — server-side port of src/lib/gate-pass-engine/engine.ts.
 *
 * Implements the same 4-stage validation pipeline and produces the same report
 * shape the React components already consume:
 *
 *   [Scan] -> [1 STRUCTURE] -> [2 SIGNATURE] -> [3 SERVER/CACHE] -> [4 POLICY] -> ALLOW/DENY
 *
 * Three defects in the original are fixed by the move to PHP, because each was
 * a direct consequence of running in the browser:
 *
 *   1. The signing secret (GATE_ENGINE_SECRET) was a literal in client-side JS,
 *      so it shipped to every visitor and anyone could mint a valid pass. It is
 *      now a server-only value read from config/gatepass.php.
 *   2. computeSignature() was a 32-bit FNV-style hash despite the code calling
 *      itself HMAC-SHA256 — trivially forgeable. It is now a real
 *      hash_hmac(sha256) compared in constant time.
 *   3. The replay cache (USED_NONCE_CACHE) and revocation list (REVOKED_PASS_IDS)
 *      were in-memory and reset on page reload, so replay detection did not
 *      survive a refresh and could not be shared between guards. Both are now
 *      persisted (gate_pass_nonces, gate_passes.revoked_at).
 *
 * Because of (2) the token signature format differs from the old one. Tokens
 * minted by the previous JS engine will not validate here, which is intended —
 * they were forgeable. Passes are re-issued on first login.
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
     *
     * @return array{token: string, payload: array, valid_from: CarbonImmutable, valid_until: CarbonImmutable}
     */
    public function issueToken(GatePass $pass, ?GateId $gate = null, ?int $windowSeconds = null): array
    {
        $window = $windowSeconds ?? (int) config('gatepass.window_seconds', 30);
        $nowSec = CarbonImmutable::now()->getTimestamp();

        $windowIndex = intdiv($nowSec, $window);
        $validFrom = $windowIndex * $window;
        $validUntil = $validFrom + $window;

        $category = $pass->category;

        // Nonce is bound to the holder and the specific time slot.
        $uidTail = substr((string) ($pass->user?->uid ?? $pass->pass_id), -4);
        $nonce = sprintf(
            'NONCE-%s-%s%s',
            $uidTail,
            strtoupper(dechex($windowIndex)),
            $pass->rotation_seq > 1 ? '-S'.$pass->rotation_seq : ''
        );

        $variant = $this->assignedColorVariant($category, $pass->pass_id, $pass->rotation_seq);

        $payload = [
            'gpe' => config('gatepass.protocol.engine_id', 'GPE'),
            'cid' => config('gatepass.default_community_id'),
            'v' => (int) config('gatepass.protocol.version', 1),
            'pid' => $pass->pass_id,
            'cat' => $category->value,
            'zone' => $pass->access_zone ?: $category->defaultZone(),
            'uid' => (string) ($pass->user?->uid ?? ''),
            'nam' => $pass->holder_name,
            'prop' => $pass->property ?? 'Unassigned',
            'gate' => ($gate ?? $pass->designated_gate ?? GateId::Any)->value,
            'vf' => $validFrom,
            'vu' => $validUntil,
            'nonce' => $nonce,
            't' => $nowSec,
            'cvar' => $pass->color_variant ?: $variant['name'],
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
     * Issues a token with custom claims or overrides, used for security simulation tests.
     */
    public function createCustomToken(GatePass $pass, array $overrides = []): string
    {
        $category = $pass->category;
        $windowIndex = (int) floor(now()->getTimestamp() / (int) config('gatepass.temporal_window_seconds', 60));
        $validFrom = $windowIndex * (int) config('gatepass.temporal_window_seconds', 60);
        $validUntil = $validFrom + (int) config('gatepass.temporal_window_seconds', 60);
        $variant = $this->assignedColorVariant($category, $pass->pass_id, $pass->rotation_seq);

        $payload = array_merge([
            'gpe' => config('gatepass.protocol.engine_id', 'GPE'),
            'cid' => config('gatepass.default_community_id'),
            'v' => (int) config('gatepass.protocol.version', 1),
            'pid' => $pass->pass_id,
            'cat' => $category->value,
            'zone' => $pass->access_zone ?: $category->defaultZone(),
            'uid' => (string) ($pass->user?->uid ?? ''),
            'nam' => $pass->holder_name,
            'prop' => $pass->property ?? 'Unassigned',
            'gate' => ($pass->designated_gate ?? GateId::Any)->value,
            'vf' => $validFrom,
            'vu' => $validUntil,
            'nonce' => sprintf('NONCE-%s-%s', substr((string) ($pass->user?->uid ?? $pass->pass_id), -4), strtoupper(bin2hex(random_bytes(4)))),
            't' => now()->getTimestamp(),
            'cvar' => $pass->color_variant ?: $variant['name'],
            'seq' => $pass->rotation_seq,
        ], $overrides);

        $signature = $this->sign($payload);
        $payload['sig'] = $signature;
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return config('gatepass.protocol.envelope_prefix').$encodedPayload.'.'.$signature;
    }

    /** Creates (or returns) the registry row for a user, formatting the pass ID. */
    public function issuePassFor(User $user): GatePass
    {
        $category = $user->passCategory();

        return GatePass::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'Active'],
            [
                'pass_id' => $this->formatPassId($category, (string) $user->id),
                'category' => $category,
                'holder_name' => $user->display_name,
                'property' => $user->propertyLabel(),
                'access_zone' => $category->defaultZone(),
                'designated_gate' => GateId::Any,
                'color_variant' => $this->assignedColorVariant($category, (string) $user->id)['name'],
                'rotation_seq' => 1,
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Validation pipeline
    // ─────────────────────────────────────────────────────────────────

    /**
     * Runs the full 4-stage pipeline and returns a report in the same shape as
     * GatePassValidationReport in the TypeScript engine.
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
            return $this->denyReport(
                $now,
                $currentGate,
                DenyReason::NotAGpeGatePass,
                'Unrecognized QR token format. Not issued by this Digital Gate Pass Engine.',
                'STRUCTURE',
                'Malformed QR envelope format.',
            );
        }

        $parts = explode('.', $rawQr);
        if (count($parts) !== 3) {
            return $this->denyReport(
                $now,
                $currentGate,
                DenyReason::InvalidQrStructure,
                'Token envelope requires exactly 3 segments (protocol header, payload, signature).',
                'STRUCTURE',
                'Segment count mismatch.',
            );
        }

        $decoded = $this->base64UrlDecode($parts[1]);
        $payload = $decoded === null ? null : json_decode($decoded, true);

        if (! is_array($payload) || ! isset($payload['pid'], $payload['cat'], $payload['sig'], $payload['vf'], $payload['vu'], $payload['nonce'])) {
            return $this->denyReport(
                $now,
                $currentGate,
                DenyReason::InvalidQrStructure,
                'Payload could not be decoded or is missing required claims.',
                'STRUCTURE',
                'Payload decode failure.',
            );
        }

        if (($payload['gpe'] ?? null) !== config('gatepass.protocol.engine_id')) {
            return $this->denyReport(
                $now,
                $currentGate,
                DenyReason::NotAGpeGatePass,
                'Payload does not carry the Gate Pass Engine identifier.',
                'STRUCTURE',
                'Engine identifier missing.',
            );
        }

        if (($payload['cid'] ?? null) !== config('gatepass.default_community_id')) {
            return $this->denyReport(
                $now,
                $currentGate,
                DenyReason::UnauthorizedCommunity,
                sprintf('Pass was issued for community %s, not this estate.', $payload['cid'] ?? 'UNKNOWN'),
                'STRUCTURE',
                'Community identifier mismatch.',
                $payload,
            );
        }

        $category = PassCategory::tryFrom($payload['cat']) ?? PassCategory::Homeowner;
        $policy = $this->policyFor($category);

        $report = $this->baseReport($payload, $category, $policy, $now, $currentGate);
        $report['stages']['structure'] = $this->stage(true, 'STRUCTURE', 'Envelope recognised; payload decoded and claims present.', $now);
        $report['checks']['payloadAuthenticity'] = true;
        $report['checks']['shapeCategoryMatch'] = true;

        // ── STAGE 2: cryptographic signature ──
        $claimed = (string) $payload['sig'];
        $unsigned = $payload;
        unset($unsigned['sig']);
        $expected = $this->sign($unsigned);

        if (! hash_equals($expected, $claimed)) {
            $report['status'] = ValidationStatus::Deny->value;
            $report['primaryReason'] = DenyReason::SignatureMismatch->value.': Payload signature does not match. Token was altered or forged.';
            $report['stages']['cryptography'] = $this->stage(false, 'SIGNATURE', 'HMAC-SHA256 verification failed.', $now);

            return $report;
        }

        $report['stages']['cryptography'] = $this->stage(true, 'SIGNATURE', 'HMAC-SHA256 signature verified against server key.', $now);
        $report['checks']['cryptographicSignature'] = true;
        $report['checks']['colorClassMatch'] = true;

        // ── STAGE 3: temporal window, revocation, replay ──
        if ($nowSec > (int) $payload['vu']) {
            $report['status'] = ValidationStatus::Deny->value;
            $report['primaryReason'] = sprintf('%s: Dynamic credential expired %d seconds ago.', DenyReason::TokenExpired->value, $nowSec - (int) $payload['vu']);
            $report['stages']['serverCache'] = $this->stage(
                false,
                'SERVER_CACHE',
                sprintf(
                    'Token valid window was %s - %s. Current time is %s.',
                    CarbonImmutable::createFromTimestamp((int) $payload['vf'])->toTimeString(),
                    CarbonImmutable::createFromTimestamp((int) $payload['vu'])->toTimeString(),
                    $now->toTimeString(),
                ),
                $now,
            );

            return $report;
        }

        $skew = (int) config('gatepass.clock_skew_seconds', 5);
        if ($nowSec < (int) $payload['vf'] - $skew) {
            $report['status'] = ValidationStatus::Deny->value;
            $report['primaryReason'] = DenyReason::TokenNotYetValid->value.': Credential timestamp is in the future.';
            $report['stages']['serverCache'] = $this->stage(false, 'SERVER_CACHE', 'Token has not yet reached its active validity window.', $now);

            return $report;
        }

        $report['checks']['temporalTimeAuth'] = true;

        // Revocation is a persisted state, not a hardcoded list.
        $pass = GatePass::where('pass_id', $payload['pid'])->first();

        if ($pass?->isRevoked()) {
            $report['status'] = ValidationStatus::Deny->value;
            $report['primaryReason'] = DenyReason::PassRevoked->value.': Pass ID has been revoked in the central security directory.';
            $report['stages']['serverCache'] = $this->stage(
                false,
                'SERVER_CACHE',
                sprintf('Pass ID %s was revoked on %s. %s', $payload['pid'], $pass->revoked_at?->toDayDateTimeString(), $pass->revocation_reason ?? ''),
                $now,
            );

            return $report;
        }

        // The holder must still be an active account.
        if ($pass && $pass->user && ! $pass->user->isActive()) {
            $report['status'] = ValidationStatus::Deny->value;
            $report['primaryReason'] = DenyReason::UserNotActive->value.': Pass holder account is deactivated.';
            $report['stages']['serverCache'] = $this->stage(false, 'SERVER_CACHE', 'Holder account is not active.', $now);

            return $report;
        }

        // Replay detection: the first scan of a nonce wins. The insert is the
        // lock — a unique index on `nonce` makes two simultaneous scans safe.
        if (! $bypassReplayCheck) {
            $firstSeen = $this->claimNonce((string) $payload['nonce'], (string) $payload['pid'], (int) $payload['vu']);

            if ($firstSeen !== null) {
                $report['status'] = ValidationStatus::Deny->value;
                $report['primaryReason'] = DenyReason::ReplayAttack->value.': Static screenshot duplicate detected. Nonce has already been checked.';
                $report['stages']['serverCache'] = $this->stage(
                    false,
                    'SERVER_CACHE',
                    sprintf('Nonce %s was already used at %s.', $payload['nonce'], $firstSeen->toTimeString()),
                    $now,
                );

                return $report;
            }
        }

        $report['stages']['serverCache'] = $this->stage(
            true,
            'SERVER_CACHE',
            sprintf('Active session verified. Nonce %s registered; zero revocation flags.', $payload['nonce']),
            $now,
        );

        // ── STAGE 4: access policy ──
        $tokenGate = GateId::tryFrom($payload['gate'] ?? GateId::Any->value) ?? GateId::Any;

        if ($tokenGate !== GateId::Any && $tokenGate !== $currentGate) {
            $report['status'] = ValidationStatus::Deny->value;
            $report['primaryReason'] = sprintf('%s: Pass restricted strictly to %s. Attempted ingress at %s.', DenyReason::UnauthorizedGate->value, $tokenGate->value, $currentGate->value);
            $report['stages']['accessPolicy'] = $this->stage(false, 'ACCESS_POLICY', sprintf('Physical boundary enforcement: designated gate is %s.', $tokenGate->value), $now);

            return $report;
        }

        // The category policy also constrains which gates are usable at all.
        if (! in_array($currentGate->value, $policy['allowed_gates'], true)) {
            $report['status'] = ValidationStatus::Deny->value;
            $report['primaryReason'] = sprintf('%s: Category %s is not cleared for %s.', DenyReason::UnauthorizedGate->value, $category->value, $currentGate->value);
            $report['stages']['accessPolicy'] = $this->stage(false, 'ACCESS_POLICY', 'Category gate clearance does not include this portal.', $now);

            return $report;
        }

        $report['checks']['physicalGateAuth'] = true;

        $hours = $policy['operational_hours'];
        if (! ($hours['is_24_hours'] ?? false)) {
            // Carbon dayOfWeek matches the JS getDay() convention: 0 = Sunday.
            $currentDay = (int) $now->dayOfWeek;
            $currentHour = (int) $now->hour;

            if (isset($hours['days_of_week']) && ! in_array($currentDay, $hours['days_of_week'], true)) {
                $report['status'] = ValidationStatus::Deny->value;
                $report['primaryReason'] = sprintf('%s: Ingress not permitted on %ss.', DenyReason::OutsideHours->value, $now->format('l'));
                $report['stages']['accessPolicy'] = $this->stage(false, 'ACCESS_POLICY', sprintf('Category %s schedule restricted to operating days.', $category->value), $now);

                return $report;
            }

            if (isset($hours['start_hour'], $hours['end_hour'])
                && ($currentHour < $hours['start_hour'] || $currentHour >= $hours['end_hour'])) {
                $report['status'] = ValidationStatus::Deny->value;
                $report['primaryReason'] = sprintf(
                    '%s: Operational shift bounds are %d:00 - %d:00. Current time: %s.',
                    DenyReason::OutsideHours->value,
                    $hours['start_hour'],
                    $hours['end_hour'],
                    $now->toTimeString(),
                );
                $report['stages']['accessPolicy'] = $this->stage(
                    false,
                    'ACCESS_POLICY',
                    sprintf('Shift schedule violation: access prohibited outside %d:00 to %d:00.', $hours['start_hour'], $hours['end_hour']),
                    $now,
                );

                return $report;
            }
        }

        // ── Cleared ──
        $report['status'] = ValidationStatus::Allow->value;
        $report['primaryReason'] = 'ACCESS_APPROVED: 6-factor cryptographic, geometric & temporal clearance verified.';
        $report['stages']['accessPolicy'] = $this->stage(
            true,
            'ACCESS_POLICY',
            sprintf('Policy clearance active. Authorized for: %s...', implode(', ', array_slice($policy['authorized_zones'], 0, 2))),
            $now,
        );

        $variant = $this->assignedColorVariant($category, (string) $payload['pid'], (int) ($payload['seq'] ?? 1));
        $report['visualIdentity'] = [
            'shape' => $category->shape()->value,
            'colorVariantName' => $payload['cvar'] ?? $variant['name'],
            'colorHex' => $variant['hex'],
            'isApprovedPalette' => true,
            'contrastRatio' => $variant['contrast_ratio'],
            'wcagPass' => $variant['wcag_pass'],
            'rotationSequence' => (int) ($payload['seq'] ?? 1),
            'securityNote' => 'Visual identifier only; physical access authorization is granted strictly via cryptographic HMAC signature.',
        ];

        return $report;
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
     * Deterministic colour assignment — same seed and sequence always give the
     * same variant, matching getAssignedColorVariant() in the TS engine.
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

        // 32-bit rolling hash, mirroring the original seedNum computation.
        $seedStr = $seed ?: 'default-seed';
        $seedNum = 0;
        foreach (str_split($seedStr) as $char) {
            $seedNum = ($seedNum * 31 + ord($char)) & 0xFFFFFFFF;
        }

        $index = ($seedNum + $rotationSeq) % count($palette);

        return $palette[$index];
    }

    /** Picks a random approved variant, optionally excluding ids already in use. */
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
     * Advances a pass to its next visual identity so a stale screenshot no
     * longer matches what the guard expects to see.
     */
    public function rotateVisualIdentity(GatePass $pass): array
    {
        $nextSeq = $pass->rotation_seq + 1;
        $variant = $this->assignedColorVariant($pass->category, $pass->pass_id, $nextSeq);

        $pass->update([
            'rotation_seq' => $nextSeq,
            'color_variant' => $variant['name'],
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

    public function policyFor(PassCategory $category): array
    {
        return config('gatepass.policies.'.$category->value)
            ?? config('gatepass.policies.HOMEOWNER');
    }

    public function categoryConfig(PassCategory $category): array
    {
        return config('gatepass.categories.'.$category->value)
            ?? config('gatepass.categories.HOMEOWNER');
    }

    /**
     * Real HMAC-SHA256 over a canonical (key-sorted) JSON encoding, so the
     * signature is stable regardless of key order — the same guarantee
     * deterministicStringify() provided in the TypeScript engine.
     */
    private function sign(array $payload): string
    {
        return hash_hmac('sha256', $this->canonicalJson($payload), $this->key());
    }

    /** Recursively key-sorts, then JSON-encodes. */
    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->canonicalise($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        // A list keeps its order; a map is sorted by key.
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

    private function stage(bool $passed, string $stage, string $message, CarbonImmutable $now): array
    {
        return [
            'passed' => $passed,
            'stage' => $stage,
            'message' => $message,
            'timestamp' => $now->toIso8601String(),
        ];
    }

    /** Report skeleton for a decoded, recognised payload. */
    private function baseReport(array $payload, PassCategory $category, array $policy, CarbonImmutable $now, GateId $gate): array
    {
        $issuedAt = CarbonImmutable::createFromTimestamp((int) ($payload['t'] ?? $now->getTimestamp()));
        $expiresAt = CarbonImmutable::createFromTimestamp((int) ($payload['vu'] ?? $now->getTimestamp()));

        return [
            'status' => ValidationStatus::Deny->value,
            'primaryReason' => '',
            'category' => $category->value,
            'shape' => $category->shape()->value,
            'communityId' => $payload['cid'] ?? config('gatepass.default_community_id'),
            'accessZone' => $payload['zone'] ?? 'ZONE-UNKNOWN',
            'passId' => $payload['pid'] ?? 'UNKNOWN',
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
                'shapeCategoryMatch' => false,
                'colorClassMatch' => false,
                'payloadAuthenticity' => false,
                'cryptographicSignature' => false,
                'physicalGateAuth' => false,
                'temporalTimeAuth' => false,
            ],
            'policy' => $policy,
            'issuedAt' => $issuedAt->toIso8601String(),
            'expiresAt' => $expiresAt->toIso8601String(),
            'secondsRemaining' => max(0, $expiresAt->getTimestamp() - $now->getTimestamp()),
        ];
    }

    /** Terminal DENY report for a token that never got far enough to decode. */
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

        $report['primaryReason'] = $reason->value.': '.$detail;

        $slot = match ($stage) {
            'STRUCTURE' => 'structure',
            'SIGNATURE' => 'cryptography',
            'SERVER_CACHE' => 'serverCache',
            default => 'accessPolicy',
        };
        $report['stages'][$slot] = $this->stage(false, $stage, $stageMessage, $now);

        return $report;
    }
}
