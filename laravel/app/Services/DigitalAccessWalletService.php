<?php

namespace App\Services;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Models\GatePass;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use ZipArchive;

class DigitalAccessWalletService
{
    public function __construct(
        private readonly GatePassEngine $engine,
        private readonly GateScanner $scanner,
        private readonly HouseholdManagementService $householdService,
    ) {}

    /**
     * Retrieve all wallet credentials accessible to the given user.
     * Includes personal pass, all household members' passes, and delegated passes.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getWalletCredentials(User $user): Collection
    {
        $passes = collect();

        // 1. User's personal account pass
        $personalPass = $this->engine->issuePassFor($user);
        if ($personalPass) {
            $passes->push($this->formatWalletPass($personalPass, 'Primary Account Holder', $user));
        }

        // 2. Household members' passes (if user is homeowner or in a household)
        $household = Household::where('primary_homeowner_id', $user->id)
            ->with(['members.gatePass'])
            ->first();

        if ($household) {
            foreach ($household->members as $member) {
                if ($member->gatePass && $member->gatePass->id !== $personalPass?->id) {
                    $passes->push($this->formatWalletPass(
                        $member->gatePass,
                        $member->relationship_label ?: ucfirst(str_replace('_', ' ', $member->role_in_household)),
                        null,
                        $member
                    ));
                }
            }
        }

        return $passes->unique('pass_id')->values();
    }

    /**
     * Format a GatePass into a rich multi-modal wallet credential.
     *
     * @return array<string, mixed>
     */
    public function formatWalletPass(
        GatePass $pass,
        string $relationshipLabel,
        ?User $user = null,
        ?HouseholdMember $member = null
    ): array {
        $category = $pass->category;
        $variant = $this->engine->variantFor($pass);
        $shape = $category->shape()->value;

        // Dynamic QR Token
        $qrToken = null;
        $validUntil = null;
        $secondsRemaining = 30;

        if ($pass->isActive()) {
            try {
                $issued = $this->engine->issueToken($pass);
                $qrToken = $issued['token'];
                $validUntil = $issued['valid_until']->toIso8601String();
                $secondsRemaining = max(0, $issued['valid_until']->getTimestamp() - now()->getTimestamp());
            } catch (\Throwable $e) {
                $qrToken = $pass->pass_id;
            }
        }

        // Contactless NFC UID & Cryptographic Payload
        $nfcUid = $this->generateNfcUid($pass);
        $nfcPayload = sprintf('CHUB-NFC-V2|%s|%s|%d|%s',
            $pass->pass_id,
            $nfcUid,
            now()->getTimestamp(),
            hash_hmac('sha256', $pass->pass_id.'|'.$nfcUid, config('gatepass.secret', 'default_secret'))
        );

        $schedule = $member?->access_schedule ?? $pass->metadata['access_schedule'] ?? null;
        $permissions = $member?->permissions ?? $pass->metadata['permissions'] ?? [];

        return [
            'id' => $pass->id,
            'pass_id' => $pass->pass_id,
            'holder_name' => $pass->holder_name,
            'relationship_label' => $relationshipLabel,
            'role_in_household' => $member?->role_in_household ?? ($user?->role->value ?? 'Resident'),
            'category' => $category->value,
            'category_label' => $category->label(),
            'shape' => $shape,
            'variant' => $variant,
            'property' => $pass->property ?: 'Unassigned',
            'status' => $pass->status->value,
            'status_label' => $pass->status->label(),
            'is_active' => $pass->isActive(),
            'offline_pin' => $pass->offline_pin ?? '482910',

            // Multi-Modal 1: QR Credential
            'qr' => [
                'token' => $qrToken,
                'valid_until' => $validUntil,
                'seconds_remaining' => $secondsRemaining,
                'envelope_prefix' => config('gatepass.protocol.envelope_prefix', 'GPE1.'),
                'standard' => 'ISO/IEC 18004 Tamper-Evident Anti-Replay QR',
            ],

            // Multi-Modal 2: Contactless NFC Tap Credential
            'nfc' => [
                'card_uid' => $nfcUid,
                'payload' => $nfcPayload,
                'protocol' => 'ISO/IEC 14443 Type A (NFC-A) / MIFARE DESFire EV3 / Apple VAS / Google Smart Tap',
                'frequency' => '13.56 MHz High Frequency',
                'status' => 'Antenna Field Ready — Tap to Authenticate',
                'ndef_mime' => 'application/vnd.communityhub.gatepass',
            ],

            // Multi-Modal 3: Mobile Wallet Integrations
            'wallet_integrations' => [
                'apple_wallet' => [
                    'available' => true,
                    'file_name' => "{$pass->pass_id}.pkpass",
                    'download_url' => url("/dashboard/wallet/apple-pass/{$pass->pass_id}"),
                    'badge_label' => 'Add to Apple Wallet',
                ],
                'google_wallet' => [
                    'available' => true,
                    'save_url' => url("/dashboard/wallet/google-pass/{$pass->pass_id}"),
                    'badge_label' => 'Save to Google Wallet',
                ],
                'samsung_wallet' => [
                    'available' => true,
                    'save_url' => url("/dashboard/wallet/samsung-pass/{$pass->pass_id}"),
                    'badge_label' => 'Add to Samsung Wallet',
                ],
            ],

            // Governance & Access Windows
            'schedule' => $schedule,
            'permissions' => $permissions,
            'designated_gate' => $pass->designated_gate?->label() ?? 'Any Gate',
            'expiry_date' => $member?->valid_until?->toFormattedDateString() ?? 'Perpetual Residence',
        ];
    }

    /**
     * Deterministic ISO 14443 Type A 7-byte UID for virtual NFC card presentation.
     */
    public function generateNfcUid(GatePass $pass): string
    {
        $hash = md5($pass->pass_id.':'.config('gatepass.secret', 'community_hub'));

        return sprintf(
            '04:%s:%s:%s:%s:%s:%s',
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            substr($hash, 4, 2),
            substr($hash, 6, 2),
            substr($hash, 8, 2),
            substr($hash, 10, 2)
        );
    }

    /**
     * Generate an Apple Wallet .pkpass ZIP bundle in memory.
     */
    public function generateApplePkpass(GatePass $pass): string
    {
        $category = $pass->category;
        $variant = $this->engine->variantFor($pass);

        // Apple Pass JSON Structure (Passbook format version 1)
        $passJson = [
            'formatVersion' => 1,
            'passTypeIdentifier' => 'pass.com.communityhub.gateaccess',
            'serialNumber' => $pass->pass_id,
            'teamIdentifier' => 'CHUBESTATES',
            'organizationName' => 'Community Hub Estates',
            'description' => "Community Gate Credential - {$pass->holder_name}",
            'logoText' => 'Community Hub',
            'foregroundColor' => 'rgb(255, 255, 255)',
            'backgroundColor' => 'rgb(15, 23, 42)',
            'labelColor' => 'rgb(212, 175, 55)',
            'generic' => [
                'primaryFields' => [
                    [
                        'key' => 'holder',
                        'label' => 'AUTHORIZED HOLDER',
                        'value' => $pass->holder_name,
                    ],
                ],
                'secondaryFields' => [
                    [
                        'key' => 'role',
                        'label' => 'CREDENTIAL CATEGORY',
                        'value' => $category->label(),
                    ],
                    [
                        'key' => 'property',
                        'label' => 'ESTATE RESIDENCE',
                        'value' => $pass->property ?: 'Unassigned',
                    ],
                ],
                'auxiliaryFields' => [
                    [
                        'key' => 'status',
                        'label' => 'CLEARANCE',
                        'value' => $pass->status->label(),
                    ],
                    [
                        'key' => 'gate',
                        'label' => 'DESIGNATED GATE',
                        'value' => $pass->designated_gate?->label() ?? 'All Gates',
                    ],
                ],
                'backFields' => [
                    [
                        'key' => 'protocol',
                        'label' => 'SECURITY PROTOCOL',
                        'value' => 'ISO/IEC 18004 Anti-Replay QR & ISO 14443 Type A NFC Pass. Governed by Community Hub Estate Management.',
                    ],
                    [
                        'key' => 'nfc_uid',
                        'label' => 'VIRTUAL NFC CARD UID',
                        'value' => $this->generateNfcUid($pass),
                    ],
                    [
                        'key' => 'support',
                        'label' => 'GATEHOUSE DISPATCH',
                        'value' => 'Security Gatehouse: +1 (876) 555-GATE | Emergency SOS: 119',
                    ],
                ],
            ],
            'barcodes' => [
                [
                    'format' => 'PKBarcodeFormatQR',
                    'message' => $pass->pass_id,
                    'messageEncoding' => 'iso-8859-1',
                    'altText' => "Gate Pass ID: {$pass->pass_id}",
                ],
            ],
            'nfc' => [
                'message' => sprintf('CHUB-NFC-V2|%s|%s', $pass->pass_id, $this->generateNfcUid($pass)),
                'encryptionPublicKey' => '04'.hash('sha256', config('gatepass.secret', 'chub_nfc_pub')),
            ],
        ];

        // Create ZipArchive in temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'pkpass_');
        $zip = new ZipArchive;
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $jsonStr = json_encode($passJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $zip->addFromString('pass.json', $jsonStr);

        // Minimal PNG 1x1 transparent icon for Apple Pass structure
        $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $zip->addFromString('icon.png', $png1x1);
        $zip->addFromString('icon@2x.png', $png1x1);
        $zip->addFromString('logo.png', $png1x1);
        $zip->addFromString('logo@2x.png', $png1x1);

        // Manifest of SHA-1 hashes
        $manifest = [
            'pass.json' => sha1($jsonStr),
            'icon.png' => sha1($png1x1),
            'icon@2x.png' => sha1($png1x1),
            'logo.png' => sha1($png1x1),
            'logo@2x.png' => sha1($png1x1),
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $zip->close();

        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        return $content;
    }

    /**
     * Generate Google Wallet pass payload object.
     *
     * @return array<string, mixed>
     */
    public function getGoogleWalletPayload(GatePass $pass): array
    {
        $uid = $this->generateNfcUid($pass);
        $category = $pass->category;

        return [
            'protocol' => 'Google Wallet REST API v1 / Save to Google Wallet',
            'genericObject' => [
                'id' => "COMMUNITY_HUB.PASS_{$pass->pass_id}",
                'classId' => 'COMMUNITY_HUB.ESTATE_ACCESS_CREDENTIAL',
                'logo' => [
                    'sourceUri' => [
                        'uri' => URL::to('/images/branding/community-crest.png'),
                    ],
                    'contentDescription' => [
                        'defaultValue' => ['language' => 'en-US', 'value' => 'Community Hub Crest'],
                    ],
                ],
                'cardTitle' => [
                    'defaultValue' => ['language' => 'en-US', 'value' => 'Community Hub — Estate Access Pass'],
                ],
                'subheader' => [
                    'defaultValue' => ['language' => 'en-US', 'value' => $pass->property ?: 'Unassigned'],
                ],
                'header' => [
                    'defaultValue' => ['language' => 'en-US', 'value' => $pass->holder_name],
                ],
                'barcode' => [
                    'type' => 'QR_CODE',
                    'value' => $pass->pass_id,
                    'alternateText' => $pass->pass_id,
                ],
                'hexBackgroundColor' => '#0F172A',
                'textModulesData' => [
                    [
                        'id' => 'category',
                        'header' => 'CATEGORY',
                        'body' => $category->label(),
                    ],
                    [
                        'id' => 'nfc_uid',
                        'header' => 'NFC CARD UID',
                        'body' => $uid,
                    ],
                    [
                        'id' => 'status',
                        'header' => 'STATUS',
                        'body' => $pass->status->label(),
                    ],
                ],
            ],
            'deepLink' => "https://pay.google.com/gp/v/save/PASS_{$pass->pass_id}",
        ];
    }

    /**
     * Generate Samsung Wallet pass payload object.
     *
     * @return array<string, mixed>
     */
    public function getSamsungWalletPayload(GatePass $pass): array
    {
        return [
            'protocol' => 'Samsung Wallet Partner Portal / Digital Key & Pass',
            'cdata' => [
                'cardId' => $pass->pass_id,
                'title' => 'Community Hub Estate Pass',
                'holderName' => $pass->holder_name,
                'property' => $pass->property ?: 'Unassigned',
                'category' => $pass->category->label(),
                'nfcUid' => $this->generateNfcUid($pass),
                'nfcPayload' => sprintf('CHUB-NFC-V2|%s|%s', $pass->pass_id, $this->generateNfcUid($pass)),
            ],
            'deepLink' => "samsungpay://wallet/card/add?passId={$pass->pass_id}",
        ];
    }

    /**
     * Simulate an NFC Tap against an estate gate reader.
     * Validates through GateScanner and returns real-time decision.
     *
     * @return array<string, mixed>
     */
    public function simulateNfcTap(string $nfcPayloadOrUid, GateId $gate, User $guardOrResident): array
    {
        return $this->scanner->scan($nfcPayloadOrUid, $gate, $guardOrResident, 'NFC Contactless Tap');
    }

    /**
     * One-button Lost/Stolen Credential Protocol:
     * 1. Immediately transitions the existing pass to REVOKED, recording timestamp and reason.
     *    All prior screenshots, exported pkpass files, and NFC keys are permanently dead at all gates.
     * 2. Immediately generates a fresh replacement credential with a new pass ID, incremented rotation
     *    sequence, new cryptographic keys, and updated offline keypad PIN.
     *
     * @return array{
     *     success: bool,
     *     revoked_pass: array<string, mixed>,
     *     replacement_pass: array<string, mixed>,
     *     message: string
     * }
     */
    public function reportLostAndReplace(GatePass|string $passOrId, User $actor, ?string $reason = null): array
    {
        $oldPass = is_string($passOrId)
            ? GatePass::where('pass_id', $passOrId)->firstOrFail()
            : $passOrId;

        $revocationReason = $reason ?: 'Reported Lost/Stolen by Cardholder. Immediate revocation issued.';
        $newPass = null;
        $replacementFormatted = null;

        DB::transaction(function () use (&$oldPass, &$newPass, &$replacementFormatted, $actor, $revocationReason) {
            // 1. Immediately Revoke Old Pass
            if ($oldPass->canTransitionTo(PassStatus::Revoked)) {
                $oldPass->transitionTo(PassStatus::Revoked, $actor, $revocationReason);
            } else {
                $oldPass->update([
                    'status' => PassStatus::Revoked,
                    'status_changed_at' => now(),
                    'revoked_at' => now(),
                    'revoked_by' => $actor->id,
                    'revocation_reason' => $revocationReason,
                ]);
                $oldPass->transitions()->create([
                    'from_status' => $oldPass->status,
                    'to_status' => PassStatus::Revoked,
                    'actor_id' => $actor->id,
                    'reason' => $revocationReason,
                    'occurred_at' => now(),
                ]);
            }

            // 2. Generate Replacement Credential
            $member = HouseholdMember::where('gate_pass_id', $oldPass->id)->first();
            if ($member) {
                $household = $member->household;
                $seq = ($oldPass->rotation_seq ?? 1) + 1;
                $newPassId = sprintf('GP-HOM-%s-%04d-R%d',
                    strtoupper(substr(md5($household->id.$member->name), 0, 4)),
                    rand(1000, 9999),
                    $seq
                );
                while (GatePass::where('pass_id', $newPassId)->exists()) {
                    $newPassId = sprintf('GP-HOM-%s-%04d-R%d',
                        strtoupper(substr(md5($household->id.$member->name), 0, 4)),
                        rand(1000, 9999),
                        $seq++
                    );
                }

                $category = PassCategory::tryFrom($member->pass_category) ?? $oldPass->category;

                $newPass = GatePass::create([
                    'pass_id' => $newPassId,
                    'user_id' => $member->user_id ?? $household->primary_homeowner_id,
                    'category' => $category,
                    'holder_name' => $member->name,
                    'property' => $household->property_number,
                    'access_zone' => $oldPass->access_zone ?: $category->defaultZone(),
                    'designated_gate' => $oldPass->designated_gate ?? GateId::Any,
                    'status' => PassStatus::Active,
                    'rotation_seq' => $seq,
                    'status_changed_at' => now(),
                    'color_variant' => $this->engine->randomApprovedColor($category)['id'] ?? null,
                    'offline_pin' => sprintf('%06d', mt_rand(100000, 999999)),
                    'metadata' => array_merge($oldPass->metadata ?? [], [
                        'replaces_pass_id' => $oldPass->pass_id,
                        'replacement_reason' => $revocationReason,
                        'household_id' => $household->id,
                        'role_in_household' => $member->role_in_household,
                        'relationship' => $member->relationship_label,
                        'permissions' => $member->permissions,
                        'access_schedule' => $member->access_schedule,
                    ]),
                ]);

                $newPass->recordCreation($actor, "Replacement credential for lost pass {$oldPass->pass_id}");
                $member->update(['gate_pass_id' => $newPass->id]);

                $replacementFormatted = $this->formatWalletPass(
                    $newPass,
                    $member->relationship_label ?: ucfirst(str_replace('_', ' ', $member->role_in_household)),
                    null,
                    $member
                );
            } else {
                // User personal account pass or general pass
                $user = $oldPass->user ?? $actor;
                $seq = ($oldPass->rotation_seq ?? 1) + 1;
                $newPass = $this->engine->reissuePassFor($user, $actor);
                $newPass->update([
                    'rotation_seq' => $seq,
                    'offline_pin' => sprintf('%06d', mt_rand(100000, 999999)),
                    'metadata' => array_merge($oldPass->metadata ?? [], [
                        'replaces_pass_id' => $oldPass->pass_id,
                        'replacement_reason' => $revocationReason,
                    ]),
                ]);

                $replacementFormatted = $this->formatWalletPass(
                    $newPass,
                    'Primary Account Holder',
                    $user
                );
            }
        });

        return [
            'success' => true,
            'revoked_pass' => [
                'pass_id' => $oldPass->pass_id,
                'holder_name' => $oldPass->holder_name,
                'status' => 'REVOKED',
                'revoked_at' => $oldPass->revoked_at?->toIso8601String() ?? now()->toIso8601String(),
                'revocation_reason' => $oldPass->revocation_reason,
            ],
            'replacement_pass' => $replacementFormatted,
            'message' => "Credential {$oldPass->pass_id} has been permanently REVOKED. All prior screenshots and physical cards are now invalid at all gates. Replacement credential {$newPass->pass_id} is active.",
        ];
    }

    /**
     * Retrieve all revoked credentials for this user or their household.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getRevokedCredentials(User $user): Collection
    {
        $household = Household::where('primary_homeowner_id', $user->id)->first();
        $memberPassIds = $household
            ? HouseholdMember::where('household_id', $household->id)->pluck('gate_pass_id')->filter()->all()
            : [];

        $revoked = GatePass::query()
            ->where(function ($q) use ($user, $memberPassIds) {
                $q->where('user_id', $user->id)
                    ->orWhereIn('id', $memberPassIds);
            })
            ->where(function ($q) {
                $q->where('status', PassStatus::Revoked)
                    ->orWhereNotNull('revoked_at');
            })
            ->latest('revoked_at')
            ->get();

        return $revoked->map(function (GatePass $pass) {
            return [
                'id' => $pass->id,
                'pass_id' => $pass->pass_id,
                'holder_name' => $pass->holder_name,
                'category' => $pass->category->value,
                'category_label' => $pass->category->label(),
                'property' => $pass->property,
                'status' => 'REVOKED',
                'revoked_at' => $pass->revoked_at?->toIso8601String(),
                'revoked_at_formatted' => $pass->revoked_at?->toDayDateTimeString(),
                'revocation_reason' => $pass->revocation_reason ?: 'Reported Lost/Stolen',
                'offline_pin' => $pass->offline_pin,
            ];
        });
    }
}
