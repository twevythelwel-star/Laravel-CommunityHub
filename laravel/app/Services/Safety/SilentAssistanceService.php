<?php

namespace App\Services\Safety;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\UserRole;
use App\Events\Safety\SilentAssistanceRequested;
use App\Models\DistressAlert;
use App\Models\DistressAlertEvent;
use App\Models\GatePass;
use App\Models\InAppNotification;
use App\Models\SafetyProfile;
use App\Models\User;
use App\Models\Visitor;
use App\Services\SmsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Discreet distress / silent assistance.
 *
 * Three ways in, none of which changes anything an observer can see:
 *  - the app: a plain "Request assistance?" confirmation;
 *  - the QR gesture: holding the QR code raises an alert and arms the
 *    credential, so its next gate scan tells the guard where the resident is;
 *  - the duress PIN: a second gate PIN that opens the gate like the normal
 *    one and raises an alert.
 *
 * Alerts go to security and administration only, never to the resident's own
 * notifications, which someone beside them could be looking at.
 */
class SilentAssistanceService
{
    public function __construct(private readonly SmsService $sms) {}

    public function profileFor(User $user): SafetyProfile
    {
        $holderType = $this->holderTypeForUser($user);
        $options = $this->configuredOptions($holderType);

        return SafetyProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'silent_alerts_enabled' => true,
                'preferred_assistance' => $options[0] ?? 'security',
                'assistance_preferences' => $options,
                'allowed_assistance' => $options,
            ]
        );
    }

    /** @return list<string> */
    public function configuredOptions(string $holderType): array
    {
        $all = (array) config('safety.community_options.'.$holderType, config('safety.assistance_by_holder.'.$holderType, ['security']));
        $allowed = array_merge(SafetyProfile::ASSISTANCE_TYPES, ['emergency_services']);

        if (array_diff($all, $allowed)) {
            Log::channel('security')->error('Invalid configured assistance option', ['holder_type' => $holderType]);
            $all = array_values(array_intersect($all, $allowed));
        }

        return array_values(array_filter($all, fn ($type) => (in_array($type, SafetyProfile::ASSISTANCE_TYPES, true)
                || ($type === 'emergency_services' && config('safety.emergency_services_enabled', false)))
            && ($type !== 'security' || config('safety.guest_assistance_options.security', true))));
    }

    private function holderTypeForUser(User $user): string
    {
        if ($user->activeAuthorizedAppointments()->exists()) {
            return 'authorized_representative';
        }

        $pass = $user->gatePasses()
            ->whereNull('visitor_id')
            ->whereNull('delegated_access_id')
            ->active()
            ->latest('id')
            ->first();
        $passType = match ($pass?->category) {
            PassCategory::Homeowner => 'homeowner',
            PassCategory::Renter => 'renter',
            PassCategory::LongTermOccupant => 'long_term_occupant',
            PassCategory::HomeownerStaff => 'domestic_staff',
            PassCategory::Staff => 'staff',
            PassCategory::Security => 'security',
            default => null,
        };

        return $passType ?? match ($user->role) {
            UserRole::Homeowner, UserRole::Admin, UserRole::SystemAdmin => 'homeowner',
            UserRole::TemporaryHomeowner => 'renter',
            UserRole::Staff => 'staff',
            UserRole::Security => 'security',
        };
    }

    /** @return array{holderType:string,label:string,holderName:string,property:?string,assistanceOptions:list<string>,accountHolder:bool,isEntityRepresentative?:bool,capacity?:string,representativeRole?:?string,ownerEntity?:?string,ownerEntityType?:?string} */
    public function accessContextFor(User $user): array
    {
        $profile = $this->profileFor($user);
        $holderType = $this->holderTypeForUser($user);
        $options = $this->optionsForAccount($user, $profile, $holderType);
        if ($user->role->isOperational()) {
            $options = array_values(array_diff($options, ['police', 'medical', 'fire', 'contact']));
        }

        $repAppointment = $user->activeAuthorizedAppointments()->with('ownershipEntity')->first();
        $ownerEntity = $repAppointment?->ownershipEntity;

        return [
            'holderType' => $holderType,
            'label' => $user->display_name,
            'holderName' => $user->display_name,
            'property' => $user->propertyLabel(),
            'assistanceOptions' => $options,
            'accountHolder' => true,
            'isEntityRepresentative' => $repAppointment !== null,
            'capacity' => $repAppointment ? 'Authorized Representative' : $user->role->label(),
            'representativeRole' => $repAppointment?->role->label(),
            'ownerEntity' => $ownerEntity?->legal_name,
            'ownerEntityType' => $ownerEntity?->entity_type?->label(),
        ];
    }

    public function accessContextForPass(GatePass $pass): ?array
    {
        $context = $this->guestContextForPass($pass);
        if (! $context) {
            return null;
        }

        if (($context['user'] ?? null) instanceof User) {
            $profile = $this->profileFor($context['user']);
            $context['assistanceOptions'] = $this->optionsForAccount($context['user'], $profile, $context['holderType']);
        }

        return $context;
    }

    /** Validate requested help against both the credential class and its own profile choices. */
    public function allowsAssistance(User $user, string $assistance): bool
    {
        return in_array($assistance, $this->optionsForUser($user), true);
    }

    /** @return list<string> */
    public function optionsForUser(User $user): array
    {
        return $this->accessContextFor($user)['assistanceOptions'];
    }

    private function optionsForAccount(User $user, SafetyProfile $profile, ?string $holderType = null): array
    {
        $holderType ??= $this->holderTypeForUser($user);
        $options = $this->configuredOptions($holderType);
        $preferences = $profile->allowed_assistance ?: $profile->assistance_preferences ?: $options;

        return array_values(array_intersect($options, $preferences));
    }

    /** Resolve an active, signed pass to a narrowly scoped guest credential. */
    public function guestContextForPass(GatePass $pass): ?array
    {
        if (! $pass->isActive() || ! $pass->isWithinValidity(now())) {
            return null;
        }

        $category = $pass->category instanceof PassCategory ? $pass->category->value : (string) $pass->category;
        $metadata = $pass->metadata ?? [];
        $holderType = match (true) {
            $category === PassCategory::Renter->value => 'renter',
            $category === PassCategory::LongTermOccupant->value => 'long_term_occupant',
            $category === PassCategory::Homeowner->value => 'homeowner',
            $category === PassCategory::Contractor->value => 'contractor',
            $category === PassCategory::HomeownerStaff->value => 'domestic_staff',
            $category === PassCategory::Staff->value => 'staff',
            $category === PassCategory::Security->value => 'security',
            $category === PassCategory::Delegate->value
                && in_array($metadata['authorization_type'] ?? null, ['legacy_contact', 'authorized_representative', 'caregiver', 'property_manager', 'family_member'], true) => 'legacy_contact',
            $category === PassCategory::Delegate->value => 'delegate',
            $category === PassCategory::Visitor->value => 'short_term_guest',
            default => null,
        };

        if ($holderType === null) {
            return null;
        }

        $visitor = $pass->visitor;
        $visitor?->loadMissing(['property', 'homeowner']);
        // Visitor passes can be issued by a homeowner. That sponsor is not the
        // credential holder and must never lend their profile or permissions.
        $accountHolder = $pass->visitor_id === null ? $pass->user : null;
        if ($pass->visitor_id !== null && (! $visitor || $visitor->is_blocked || $visitor->status->value === 'Checked Out' || $visitor->expired_at?->isPast())) {
            return null;
        }
        if ($pass->visitor_id === null && ! $accountHolder) {
            return null;
        }
        $options = $this->configuredOptions($holderType);
        if ($accountHolder) {
            $profile = $this->profileFor($accountHolder);
            $options = $this->optionsForAccount($accountHolder, $profile, $holderType);
        }
        if (! $options) {
            return null;
        }

        return [
            'holderType' => $holderType,
            'label' => match ($holderType) {
                'short_term_guest' => 'Short-term guest',
                'renter' => 'Renter',
                'legacy_contact' => 'Legacy contact',
                'domestic_staff' => 'Domestic staff',
                default => ucfirst(str_replace('_', ' ', $holderType)),
            },
            'holderName' => $pass->holder_name ?: ($visitor?->name ?: $accountHolder?->display_name),
            'property' => $pass->property ?: ($visitor?->property?->label() ?: ($visitor?->homeowner?->propertyLabel() ?: $accountHolder?->propertyLabel())),
            'assistanceOptions' => $options,
            'accountHolder' => $accountHolder !== null,
            'pass' => $pass,
            'visitor' => $visitor,
            'user' => $accountHolder,
        ];
    }

    /** Resolve a private guest-pass link to its currently valid visitor pass. */
    public function passForVisitorToken(string $token): ?GatePass
    {
        $visitor = Visitor::with(['gatePass', 'property', 'homeowner'])
            ->where('share_token', $token)
            ->first();
        $pass = $visitor?->gatePass;

        if (! $visitor || ! $pass || $visitor->is_blocked || $visitor->status->value === 'Checked Out'
            || $visitor->expired_at?->isPast() || ! $pass->isActive() || ! $pass->isWithinValidity(now())) {
            return null;
        }

        return $this->accessContextForPass($pass) ? $pass : null;
    }

    /** Resolve an authorized non-resident pass by its short-lived signed QR token. */
    public function passForCredentialToken(string $rawToken, GatePassEngine $engine): ?GatePass
    {
        $report = $engine->validate($rawToken, bypassReplayCheck: true);
        foreach (['cryptographicSignature', 'passRegistered', 'statusEligible', 'validityPeriod', 'notRevoked', 'personVerified'] as $check) {
            if (($report['checks'][$check] ?? false) !== true) {
                return null;
            }
        }

        $pass = GatePass::with(['user', 'visitor.property', 'visitor.homeowner'])
            ->where('pass_id', $report['passId'] ?? null)
            ->first();

        if (! $pass || $pass->user?->role->isResident() || $pass->user?->role->isAdministrative()) {
            return null;
        }

        if (! $this->accessContextForPass($pass)) {
            return null;
        }

        return $pass;
    }

    /** Resolve a currently usable guest or contractor pass without requiring a resident account. */
    public function guestProfile(GatePass $pass, array $context): SafetyProfile
    {
        if (! $context['assistanceOptions']) {
            throw new \DomainException('Safety assistance is not enabled for this credential type.');
        }

        $identity = $pass->visitor_id
            ? ['visitor_id' => $pass->visitor_id]
            : ($pass->user_id ? ['user_id' => $pass->user_id] : ['gate_pass_id' => $pass->id]);
        $defaults = [
            'user_id' => null,
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'preferred_assistance' => $context['assistanceOptions'][0] ?? 'security',
            'allowed_assistance' => $context['assistanceOptions'],
            'assistance_preferences' => $context['assistanceOptions'],
            'silent_alerts_enabled' => true,
        ];
        if ($pass->visitor_id === null && $pass->user_id !== null) {
            $defaults['user_id'] = $pass->user_id;
        }
        $profile = SafetyProfile::firstOrCreate($identity, $defaults);

        return $profile;
    }

    public function raiseForGuest(GatePass $pass, array $context, string $assistance, array $requestContext = []): ?DistressAlert
    {
        if (! $pass->isActive() || ! $pass->isWithinValidity(now())
            || (! empty($context['visitor']) && ($context['visitor']->is_blocked || $context['visitor']->status->value === 'Checked Out' || $context['visitor']->expired_at?->isPast()))
            || ! in_array($assistance, $context['assistanceOptions'], true)
            || ! in_array($assistance, $this->configuredOptions($context['holderType']), true)
            || (in_array($assistance, ['police', 'medical', 'fire', 'emergency_services'], true) && ! config('safety.emergency_services_enabled', false))
            || (! empty($context['user']) && ! in_array($assistance, $this->optionsForAccount(
                $context['user'],
                $this->profileFor($context['user']),
                $context['holderType'],
            ), true))) {
            return null;
        }

        $user = $context['user'] ?? null;
        $profile = $user ? $this->profileFor($user) : $this->guestProfile($pass, $context);
        $requestContext['gate_pass_id'] = $pass->id;
        $requestContext['credential_reference'] = $pass->pass_id;

        $alert = DistressAlert::create([
            'user_id' => $user?->id,
            'safety_profile_id' => $profile->id,
            'visitor_id' => $pass->visitor_id,
            'gate_pass_id' => $pass->id,
            'incident_id' => DistressAlert::newIncidentId(),
            'channel' => 'credential',
            'assistance_type' => $assistance,
            'severity_level' => DistressAlert::SEVERITY_URGENT_ASSISTANCE,
            'status' => 'open',
            'credential_reference' => $pass->pass_id,
            'triggered_at' => now(),
            'acknowledgment_deadline_at' => now()->addSeconds((int) config('safety.acknowledgment_timeout_seconds', 60)),
            'escalation_level' => DistressAlert::ESCALATION_SILENT_ALERT,
            'notes' => $requestContext['notes'] ?? null,
        ]);

        $this->recordAudit($alert, DistressAlertEvent::ACTIVATED, snapshot: $this->incidentSnapshot($alert) + [
            'resident' => $context['holderName'],
            'holderType' => $context['holderType'],
            'property' => $context['property'],
            'credential' => 'Verified active credential',
            'credentialReference' => $pass->pass_id,
            'assistance' => ucfirst(str_replace('_', ' ', $assistance)),
        ]);

        if ($user) {
            $this->dispatch($alert, $profile);
        } else {
            $this->dispatchForCredential($alert, $context);
        }

        return $alert;
    }

    public function accountIncidents(User $user): Collection
    {
        $this->profileFor($user);

        return DistressAlert::query()->with('auditEvents')
            ->where('user_id', $user->id)
            ->latest('triggered_at')
            ->limit(50)
            ->get();
    }

    public function raiseForAccountCredential(User $user, GatePass $pass, string $assistance, array $requestContext = []): ?DistressAlert
    {
        if ($pass->user_id !== $user->id || $pass->visitor_id || ! $pass->isActive() || ! $pass->isWithinValidity(now())) {
            return null;
        }

        $context = $this->guestContextForPass($pass);
        if (! $context || ! in_array($assistance, $context['assistanceOptions'], true)) {
            return null;
        }
        $context['user'] = $user;

        return $this->raiseForGuest($pass, $context, $assistance, $requestContext);
    }

    public function guestIncidents(GatePass $pass): Collection
    {
        if ($pass->visitor_id) {
            return DistressAlert::query()->with('auditEvents')->where('visitor_id', $pass->visitor_id)->latest('triggered_at')->limit(50)->get();
        }

        if ($pass->user_id) {
            return DistressAlert::query()->with('auditEvents')->where('user_id', $pass->user_id)->latest('triggered_at')->limit(50)->get();
        }

        return DistressAlert::query()->with('auditEvents')->where('gate_pass_id', $pass->id)->latest('triggered_at')->limit(50)->get();
    }

    private function dispatchForCredential(DistressAlert $alert, array $context): void
    {
        $priority = $this->determinePriority($alert->severity_level);
        $incidentReport = sprintf(
            "DISCREET CREDENTIAL ASSISTANCE\nIncident ID: %s\n\nCredential holder: %s (%s)\nProperty: %s\nCredential: %s\nAssistance: %s\nStatus: SECURITY ALERTED",
            $alert->incident_id,
            $context['holderName'] ?? 'Unknown credential holder',
            $context['label'] ?? 'Authorized credential',
            $context['property'] ?? 'Unknown',
            $alert->credential_reference,
            ucfirst(str_replace('_', ' ', $alert->assistance_type)),
        );
        $responderIds = User::query()
            ->whereIn('role', [UserRole::Security->value, UserRole::Admin->value, UserRole::SystemAdmin->value])
            ->where('status', 'Active')
            ->pluck('id');

        $this->recordAudit($alert, DistressAlertEvent::DISPATCH_QUEUED, snapshot: [
            'queuedAt' => now()->toIso8601String(),
            'recipientCount' => $responderIds->count(),
            'channel' => 'credential',
            'assistance' => $alert->assistance_type,
        ]);

        foreach ($responderIds as $responderId) {
            InAppNotification::create([
                'user_id' => $responderId,
                'category' => 'security',
                'title' => sprintf('URGENT - %s ASSISTANCE', strtoupper($alert->assistance_type)),
                'body' => $incidentReport,
                'action_url' => '/dashboard/silent-assistance',
                'priority' => $priority,
                'data' => [
                    'distress_alert_id' => $alert->id,
                    'incident_id' => $alert->incident_id,
                    'credential_reference' => $alert->credential_reference,
                    'holder_type' => $context['holderType'],
                    'assistance' => $alert->assistance_type,
                ],
            ]);
        }

        $this->recordAudit($alert, DistressAlertEvent::DISPATCH_SENT, snapshot: [
            'sentAt' => now()->toIso8601String(),
            'recipientCount' => $responderIds->count(),
            'channels' => ['in_app', 'security_dispatch'],
        ]);

        $this->broadcast($alert);
        $this->recordAudit($alert, DistressAlertEvent::DISPATCH_DELIVERED, snapshot: [
            'deliveredAt' => now()->toIso8601String(),
            'recipientCount' => $responderIds->count(),
        ]);
        $this->recordAudit($alert, DistressAlertEvent::SECURITY_NOTIFIED, snapshot: [
            'notifiedAt' => now()->toIso8601String(),
            'recipientCount' => $responderIds->count(),
            'channels' => ['in_app', 'security_dispatch'],
        ]);

        Log::channel('security')->alert('Credential holder requested assistance', [
            'incident_id' => $alert->incident_id,
            'credential_reference' => $alert->credential_reference,
            'holder_type' => $context['holderType'],
            'assistance' => $alert->assistance_type,
        ]);
    }

    /**
     * Raises a request. A hidden trigger (QR gesture, duress PIN) does nothing
     * for a resident who has turned silent alerts off; asking in the app always
     * works.
     *
     * @param  array{gate?: string|null, event_type?: string|null, credential_reference?: string|null, vehicle_context?: array<int, string>, severity_level?: string|null, latitude?: float|null, longitude?: float|null, notes?: string|null, gate_pass_id?: int|null}  $context
     */
    public function raise(User $user, string $channel, ?string $assistance = null, array $context = []): ?DistressAlert
    {
        $profile = $this->profileFor($user);
        $accountContext = $this->accessContextFor($user);
        $effectiveOptions = $accountContext['assistanceOptions'];
        if ($user->role->isOperational() && $channel !== 'app') {
            return null;
        }

        if ($channel !== 'app' && ! $profile->silent_alerts_enabled) {
            return null;
        }

        if ($assistance !== null && ! in_array($assistance, $effectiveOptions, true)) {
            return null;
        }

        $alert = DistressAlert::create([
            'user_id' => $user->id,
            'safety_profile_id' => $profile->id,
            'incident_id' => DistressAlert::newIncidentId(),
            'gate_pass_id' => $context['gate_pass_id'] ?? null,
            'channel' => $channel,
            'assistance_type' => $assistance ?? $profile->preferred_assistance,
            'severity_level' => in_array($context['severity_level'] ?? null, [
                DistressAlert::SEVERITY_ASSISTANCE,
                DistressAlert::SEVERITY_URGENT_ASSISTANCE,
                DistressAlert::SEVERITY_SILENT_EMERGENCY,
            ], true)
                ? $context['severity_level']
                : ($channel === 'duress_pin' ? DistressAlert::SEVERITY_SILENT_EMERGENCY : DistressAlert::SEVERITY_ASSISTANCE),
            'status' => 'open',
            'gate' => $context['gate'] ?? null,
            'event_type' => $context['event_type'] ?? null,
            'event_at' => $context['event_at'] ?? null,
            'credential_reference' => $context['credential_reference'] ?? null,
            'vehicle_context' => $context['vehicle_context'] ?? null,
            'latitude' => $context['latitude'] ?? null,
            'longitude' => $context['longitude'] ?? null,
            'notes' => $context['notes'] ?? null,
            'triggered_at' => now(),
            'acknowledgment_deadline_at' => now()->addSeconds((int) config('safety.acknowledgment_timeout_seconds', 60)),
            'escalation_level' => DistressAlert::ESCALATION_SILENT_ALERT,
        ]);

        $this->recordAudit($alert, DistressAlertEvent::ACTIVATED, snapshot: $this->incidentSnapshot($alert));

        $this->dispatch($alert, $profile);

        return $alert;
    }

    /**
     * The QR gesture: raise an alert now, and arm the credential so that the
     * resident's next gate scan places them at that gate.
     */
    public function arm(User $user, array $context = []): ?DistressAlert
    {
        $alert = $this->raise($user, 'qr_gesture', null, $context);

        if ($alert) {
            $this->profileFor($user)->forceFill([
                'armed_until' => now()->addMinutes((int) config('safety.armed_minutes', 15)),
            ])->save();
        }

        return $alert;
    }

    /**
     * Called for every scan the gate accepts or reviews. An armed credential
     * is disarmed and its alert told which gate; the guard's screen says so.
     */
    public function consumeArmedScan(GatePass $pass, GateId $gate, string $direction = 'in'): ?DistressAlert
    {
        $user = $pass->user;
        if (! $user || $pass->visitor_id) {
            return null;
        }

        $profile = SafetyProfile::where('user_id', $user->id)->first();
        if (! $profile?->isArmed()) {
            return null;
        }

        $profile->forceFill(['armed_until' => null])->save();

        $alert = DistressAlert::where('user_id', $user->id)
            ->where('channel', 'qr_gesture')
            ->active()
            ->latest('triggered_at')
            ->first();

        if (! $alert) {
            return $this->raise($user, 'qr_scan', null, $this->scanContext($pass, $gate, $direction));
        }

        $alert->update([
            ...$this->scanContext($pass, $gate, $direction),
            'notes' => trim(($alert->notes ? $alert->notes."\n" : '').'Credential scanned at '.$gate->label().' at '.now()->format('H:i').'.'),
        ]);
        $this->recordAudit($alert, DistressAlertEvent::GATE_CONTEXT_CAPTURED, snapshot: $this->incidentSnapshot($alert));
        $this->broadcast($alert);

        return $alert;
    }

    /** Capture verified scan details and registered vehicle descriptions for responders. */
    public function scanContext(GatePass $pass, GateId $gate, string $direction): array
    {
        $pass->loadMissing('user.vehicles');

        return [
            'gate' => $gate->value,
            'gate_pass_id' => $pass->id,
            'event_type' => $direction === 'out' ? 'exit_attempt' : 'entry_attempt',
            'event_at' => now(),
            'severity_level' => DistressAlert::SEVERITY_URGENT_ASSISTANCE,
            'credential_reference' => $pass->pass_id,
            'vehicle_context' => $pass->user?->vehicles->map(fn ($vehicle) => trim(implode(' ', array_filter([
                $vehicle->color,
                $vehicle->year,
                $vehicle->make,
                $vehicle->model,
                $vehicle->license_plate ? '('.$vehicle->formattedPlate().')' : null,
            ]))))->filter()->values()->all() ?? [],
        ];
    }

    /**
     * Sets the duress PIN. It must differ from the resident's own gate PIN,
     * and from every other resident's duress PIN, since the gate tells them
     * apart by the PIN alone.
     *
     * @throws \DomainException
     */
    public function setDuressPin(User $user, string $pin): void
    {
        if (! preg_match('/^\d{6}$/', $pin)) {
            throw new \DomainException('A duress PIN is six digits.');
        }

        $ownPins = $user->gatePasses()->whereNull('visitor_id')->get()->map->offline_pin;
        if ($ownPins->contains($pin)) {
            throw new \DomainException('Your duress PIN must be different from your normal gate PIN.');
        }

        $hash = $this->hashPin($pin);
        $profile = $this->profileFor($user);

        if (SafetyProfile::where('duress_pin_hash', $hash)->where('id', '!=', $profile->id)->exists()) {
            // Said plainly, without revealing whose it is.
            throw new \DomainException('That PIN is not available. Choose another.');
        }

        $profile->forceFill(['duress_pin_hash' => $hash])->save();
    }

    public function clearDuressPin(User $user): void
    {
        $this->profileFor($user)->forceFill(['duress_pin_hash' => null])->save();
    }

    /** The profile whose duress PIN this is, if any. */
    public function profileForDuressPin(string $pin): ?SafetyProfile
    {
        if (! preg_match('/^\d{6}$/', $pin)) {
            return null;
        }

        return SafetyProfile::with('user')->where('duress_pin_hash', $this->hashPin($pin))->first();
    }

    /** A new Safety Credential ID; the old one stops resolving. */
    public function rotateCredential(User $user): SafetyProfile
    {
        $profile = $this->profileFor($user);
        $profile->forceFill(['safety_credential_id' => SafetyProfile::newCredentialId()])->save();

        return $profile;
    }

    public function acknowledge(DistressAlert $alert, User $responder): void
    {
        if ($alert->status !== 'open') {
            return;
        }

        $alert->forceFill([
            'status' => 'acknowledged',
            'severity_level' => $alert->severity_level === DistressAlert::SEVERITY_ASSISTANCE
                ? DistressAlert::SEVERITY_URGENT_ASSISTANCE
                : $alert->severity_level,
            'acknowledged_by' => $responder->id,
            'acknowledged_at' => now(),
        ])->save();

        $this->recordAudit($alert, DistressAlertEvent::ACKNOWLEDGED, $responder, [
            'acknowledgedAt' => $alert->acknowledged_at?->toIso8601String(),
            'responder' => $responder->display_name,
        ]);
    }

    public function resolve(DistressAlert $alert, User $responder, string $outcome, ?string $notes): void
    {
        $alert->forceFill([
            'status' => $outcome === 'false_alarm' ? 'false_alarm' : 'resolved',
            'acknowledged_by' => $alert->acknowledged_by ?? $responder->id,
            'acknowledged_at' => $alert->acknowledged_at ?? now(),
            'resolved_by' => $responder->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ])->save();

        $this->recordAudit($alert, DistressAlertEvent::CLOSED, $responder, [
            'closedAt' => $alert->resolved_at?->toIso8601String(),
            'outcome' => $alert->status,
            'responder' => $responder->display_name,
        ]);
    }

    /**
     * Escalate an alert to Level 2 (Security Escalation).
     * Security confirms and begins the emergency procedure.
     */
    public function escalateToSecurity(DistressAlert $alert, User $responder, ?string $notes = null): void
    {
        if (! $alert->canEscalateToSecurity()) {
            throw new \DomainException('Alert cannot be escalated to security. Current level: '.$alert->escalation_level);
        }

        $alert->forceFill([
            'escalation_level' => DistressAlert::ESCALATION_SECURITY,
            'escalated_at' => now(),
            'escalated_by' => $responder->id,
            'notes' => trim(($alert->notes ? $alert->notes."\n" : '').($notes ? 'Escalation note: '.$notes : '')),
        ])->save();

        $this->recordAudit($alert, DistressAlertEvent::RESPONSE_INITIATED, $responder, [
            'initiatedAt' => $alert->escalated_at?->toIso8601String(),
            'responder' => $responder->display_name,
        ]);

        Log::channel('security')->warning('Distress alert escalated to security', [
            'alert_id' => $alert->id,
            'escalated_by' => $responder->id,
            'escalation_level' => $alert->escalation_level,
        ]);
    }

    /**
     * Escalate an alert that remained unacknowledged past its acknowledgment deadline.
     */
    public function escalateOnAcknowledgmentTimeout(DistressAlert $alert, ?string $reason = null): void
    {
        if ($alert->status !== 'open') {
            return;
        }

        if ($alert->escalation_level !== DistressAlert::ESCALATION_SILENT_ALERT && $alert->escalation_level !== null) {
            return;
        }

        $reason = $reason ?? 'Automatic escalation: Alert remained unacknowledged past deadline.';

        $alert->forceFill([
            'escalation_level' => DistressAlert::ESCALATION_SECURITY,
            'severity_level' => DistressAlert::SEVERITY_SILENT_EMERGENCY,
            'escalated_at' => now(),
            'notes' => trim(($alert->notes ? $alert->notes."\n" : '').'[TIMEOUT ESCALATION] '.$reason),
        ])->save();

        $this->recordAudit($alert, DistressAlertEvent::ACKNOWLEDGMENT_TIMEOUT_ESCALATED, null, [
            'escalatedAt' => $alert->escalated_at?->toIso8601String(),
            'reason' => $reason,
            'triggeredAt' => $alert->triggered_at?->toIso8601String(),
            'deadlineAt' => $alert->acknowledgment_deadline_at?->toIso8601String(),
            'timeoutSeconds' => (int) config('safety.acknowledgment_timeout_seconds', 60),
        ]);

        $this->broadcast($alert);

        Log::channel('security')->critical('Distress alert automatically escalated due to unacknowledged timeout', [
            'alert_id' => $alert->id,
            'incident_id' => $alert->incident_id,
            'reason' => $reason,
        ]);
    }

    /**
     * Scan and auto-escalate all unacknowledged open alerts exceeding their acknowledgment deadlines.
     */
    public function escalateUnacknowledgedAlerts(): int
    {
        $timeoutSeconds = (int) config('safety.acknowledgment_timeout_seconds', 60);
        $threshold = now()->subSeconds($timeoutSeconds);

        $alerts = DistressAlert::query()
            ->where('status', 'open')
            ->where(function ($q) {
                $q->whereNull('escalation_level')
                    ->orWhere('escalation_level', DistressAlert::ESCALATION_SILENT_ALERT);
            })
            ->where(function ($q) use ($threshold) {
                $q->where('acknowledgment_deadline_at', '<=', now())
                    ->orWhere(function ($sub) use ($threshold) {
                        $sub->whereNull('acknowledgment_deadline_at')
                            ->where('triggered_at', '<=', $threshold);
                    });
            })
            ->get();

        $count = 0;
        foreach ($alerts as $alert) {
            $this->escalateOnAcknowledgmentTimeout($alert);
            $count++;
        }

        return $count;
    }

    /**
     * Escalate an alert to Level 3 (Emergency Services).
     * Only eligible assistance types (police, medical, fire) can be escalated.
     * Requires explicit confirmation from authorized security personnel.
     */
    public function escalateToEmergencyServices(DistressAlert $alert, User $responder, string $confirmedService, ?string $notes = null): void
    {
        if (! config('safety.emergency_services_enabled', false)) {
            throw new \DomainException('Emergency services escalation is disabled for this community.');
        }

        if (! $alert->canEscalateToEmergencyServices()) {
            throw new \DomainException('Alert cannot be escalated to emergency services. Current level: '.$alert->escalation_level);
        }

        if (! $alert->isEmergencyServicesEligible()) {
            throw new \DomainException('This alert type is not eligible for emergency services escalation. Type: '.$alert->assistance_type);
        }

        if (! in_array($confirmedService, ['police', 'medical', 'fire'], true)) {
            throw new \DomainException('Invalid emergency service. Must be: police, medical, or fire');
        }

        $alert->forceFill([
            'escalation_level' => DistressAlert::ESCALATION_EMERGENCY_SERVICES,
            'severity_level' => DistressAlert::SEVERITY_EMERGENCY_SERVICES,
            'escalated_at' => now(),
            'escalated_by' => $responder->id,
            'notes' => trim(($alert->notes ? $alert->notes."\n" : '').sprintf('Emergency services escalation: %s. %s', $confirmedService, $notes ? 'Note: '.$notes : '')),
        ])->save();

        $this->recordAudit($alert, DistressAlertEvent::EMERGENCY_SERVICES_ESCALATED, $responder, [
            'escalatedAt' => $alert->escalated_at?->toIso8601String(),
            'service' => $confirmedService,
            'responder' => $responder->display_name,
            'callingRemainsManual' => true,
        ]);

        Log::channel('security')->critical('Distress alert escalated to emergency services', [
            'alert_id' => $alert->id,
            'escalated_by' => $responder->id,
            'escalation_level' => $alert->escalation_level,
            'emergency_service' => $confirmedService,
        ]);

        // Optionally dispatch notification to authorized emergency contacts if configured
        $this->notifyEmergencyContacts($alert, $confirmedService);
    }

    /**
     * Notify configured emergency contacts when escalation to emergency services occurs.
     * This is a safeguard - actual emergency services calling is done by authorized personnel.
     */
    private function notifyEmergencyContacts(DistressAlert $alert, string $service): void
    {
        if (! config('safety.emergency_services_enabled', false)) {
            return;
        }

        if (! $alert->user_id || ! $alert->safetyProfile?->allow_contact_sharing) {
            return;
        }

        $user = $alert->user;
        if (! $user) {
            return;
        }
        $profile = $this->profileFor($user);

        if (! $profile->emergency_contact_phone || ! $this->sms->isConfigured()) {
            return;
        }

        rescue(fn () => $this->sms->send(
            $profile->emergency_contact_phone,
            sprintf('EMERGENCY: %s has requested %s assistance. Estate security has escalated to emergency services. Location: %s', $user->display_name, $service, $user->propertyLabel())
        ), report: true);
    }

    private function alertContext(DistressAlert $alert): array
    {
        $pass = $alert->gatePass()->with(['user', 'visitor.property', 'visitor.homeowner'])->first();

        return $pass ? ($this->accessContextForPass($pass) ?? []) : [];
    }

    private function hashPin(string $pin): string
    {
        return hash_hmac('sha256', 'duress-pin:'.$pin, (string) config('safety.duress_pin_key'));
    }

    private function dispatch(DistressAlert $alert, SafetyProfile $profile): void
    {
        $user = $alert->user;
        if (! $user) {
            return;
        }

        $priority = $this->determinePriority($alert->severity_level);
        $incidentReport = $this->formatIncidentReport($alert, $user, $priority);

        $responderIds = User::query()
            ->whereIn('role', [UserRole::Security->value, UserRole::Admin->value, UserRole::SystemAdmin->value])
            ->where('status', 'Active')
            ->when($user, fn ($query) => $query->where('id', '!=', $user->id))
            ->pluck('id')
            ->all();

        $this->recordAudit($alert, DistressAlertEvent::DISPATCH_QUEUED, snapshot: [
            'queuedAt' => now()->toIso8601String(),
            'assistanceType' => $alert->assistance_type,
            'severity' => $alert->severity_level,
            'recipientCount' => count($responderIds),
        ]);

        foreach ($responderIds as $responderId) {
            InAppNotification::create([
                'user_id' => $responderId,
                'category' => 'security',
                'title' => $incidentReport['title'],
                'body' => $incidentReport['body'],
                'action_url' => '/dashboard/silent-assistance',
                'priority' => $priority,
                'data' => [
                    'distress_alert_id' => $alert->id,
                    'incident_id' => $alert->incident_id,
                    'incident_type' => $alert->assistance_type,
                    'severity' => $alert->severity_level,
                    'severity_label' => $alert->severityLabel(),
                    'priority' => $priority,
                    'resident_name' => $user->display_name,
                    'property' => $user->propertyLabel(),
                    'gate' => $alert->gate,
                    'event_type' => $alert->event_type,
                    'event_at' => $alert->event_at?->toIso8601String(),
                    'credential_reference' => $alert->credential_reference,
                    'vehicle_context' => $alert->vehicle_context,
                    'channel' => $alert->channel,
                    'escalation_level' => $alert->escalation_level,
                    'escalation_label' => $alert->escalationLabel(),
                ],
            ]);
        }

        $this->recordAudit($alert, DistressAlertEvent::DISPATCH_SENT, snapshot: [
            'sentAt' => now()->toIso8601String(),
            'recipientCount' => count($responderIds),
            'channels' => ['in_app', 'security_dispatch'],
        ]);

        $this->broadcast($alert);
        $this->recordAudit($alert, DistressAlertEvent::DISPATCH_DELIVERED, snapshot: [
            'deliveredAt' => now()->toIso8601String(),
            'recipientCount' => count($responderIds),
        ]);
        $this->recordAudit($alert, DistressAlertEvent::SECURITY_NOTIFIED, snapshot: [
            'notifiedAt' => now()->toIso8601String(),
            'recipientCount' => count($responderIds),
            'channels' => ['in_app', 'security_dispatch'],
        ]);

        // "Emergency contact" means the resident wants their contact told.
        if ($alert->user_id && $profile->allow_contact_sharing && $alert->assistance_type === 'contact' && $profile->emergency_contact_phone && $this->sms->isConfigured()) {
            rescue(fn () => $this->sms->send(
                $profile->emergency_contact_phone,
                sprintf('%s has asked for you to be contacted through the estate\'s assistance service. Estate security has been alerted.', $user->display_name)
            ), report: true);
        }

        Log::channel('security')->alert('Silent assistance requested', [
            'alert_id' => $alert->id,
            'channel' => $alert->channel,
            'assistance' => $alert->assistance_type,
            'gate' => $alert->gate,
            'event_type' => $alert->event_type,
            'credential_reference' => $alert->credential_reference,
            'vehicle_context' => $alert->vehicle_context,
            'priority' => $priority,
            'incident_report' => $incidentReport,
        ]);
    }

    /**
     * Determine priority level based on assistance type.
     */
    private function determinePriority(string $severity): string
    {
        return match ($severity) {
            DistressAlert::SEVERITY_URGENT_ASSISTANCE => 'high',
            DistressAlert::SEVERITY_SILENT_EMERGENCY, DistressAlert::SEVERITY_EMERGENCY_SERVICES => 'urgent',
            default => 'normal',
        };
    }

    /**
     * Format a structured incident report for security personnel.
     */
    private function formatIncidentReport(DistressAlert $alert, User $user, string $priority): array
    {
        $gateLabel = $alert->gate
            ? config('gatepass.gates.'.$alert->gate, GateId::tryFrom($alert->gate)?->label() ?? $alert->gate)
            : 'Unknown';
        $currentEvent = $this->determineCurrentEvent($alert->channel, $alert->gate);
        $credentialStatus = $alert->credential_reference
            ? 'Verified'
            : $this->determineCredentialStatus($alert->channel);

        if (! $user) {
            $holder = $alert->visitor?->name ?? 'Credential holder';
            $report = sprintf(
                "DISCREET CREDENTIAL ASSISTANCE\nIncident ID: %s\n\nCredential holder: %s\nProperty: %s\nGate: %s\nCredential: %s\nAssistance: %s\nStatus: SECURITY ALERTED",
                $alert->incident_id,
                $holder,
                $alert->propertyLabel() ?? 'Unknown',
                $gateLabel,
                $credentialStatus,
                ucfirst(str_replace('_', ' ', $alert->assistance_type)),
            );

            return [
                'title' => sprintf('%s - %s ASSISTANCE', strtoupper($alert->severityLabel()), strtoupper($alert->assistance_type)),
                'body' => $report,
                'priority' => $priority,
            ];
        }

        $title = sprintf('%s - %s ASSISTANCE', strtoupper($alert->severityLabel()), strtoupper($alert->assistance_type));

        $repAppointment = $user->activeAuthorizedAppointments()->with('ownershipEntity')->first();
        $isEntityRep = $repAppointment && $repAppointment->ownershipEntity;
        $ownerEntity = $isEntityRep ? $repAppointment->ownershipEntity : null;
        $capacity = $isEntityRep ? 'Authorized Representative' : $user->role->label();
        $ownerEntityName = $ownerEntity?->legal_name;

        if ($isEntityRep) {
            $body = sprintf(
                "DISCREET ASSISTANCE REQUEST\n".
                "Incident ID: %s\n\n".
                "Person: %s\n".
                "Capacity: %s\n".
                "Representative Role: %s\n".
                "Owner Entity: %s\n".
                "Property: %s\n".
                "Current event: %s\n".
                "Gate: %s\n".
                "Credential: %s\n".
                "Assistance: %s\n".
                "Status: %s\n".
                "Severity: %s\n".
                "Priority: %s\n".
                "Escalation Level: %s\n\n".
                "Channel: %s\n".
                'Triggered at: %s',
                $alert->incident_id,
                $user->display_name,
                $capacity,
                $repAppointment->role->label(),
                $ownerEntityName,
                $alert->propertyLabel() ?? $user->propertyLabel(),
                $alert->event_type ? strtoupper(str_replace('_', ' ', $alert->event_type)) : $currentEvent,
                $gateLabel,
                $credentialStatus,
                ucfirst($alert->assistance_type),
                in_array($alert->status, ['open', 'acknowledged'], true) ? 'ACTIVE' : strtoupper(str_replace('_', ' ', $alert->status)),
                strtoupper($alert->severityLabel()),
                strtoupper($priority),
                $alert->escalationLabel(),
                ucfirst(str_replace('_', ' ', $alert->channel)),
                $alert->triggered_at->format('H:i')
            );
        } else {
            $body = sprintf(
                "DISCREET ASSISTANCE REQUEST\n".
                "Incident ID: %s\n\n".
                "Resident: %s\n".
                "Property: %s\n".
                "Current event: %s\n".
                "Gate: %s\n".
                "Credential: %s\n".
                "Assistance: %s\n".
                "Severity: %s\n".
                "Priority: %s\n".
                "Escalation Level: %s\n\n".
                "Channel: %s\n".
                'Triggered at: %s',
                $alert->incident_id,
                $user->display_name,
                $user->propertyLabel(),
                $alert->event_type ? strtoupper(str_replace('_', ' ', $alert->event_type)) : $currentEvent,
                $gateLabel,
                $credentialStatus,
                ucfirst($alert->assistance_type),
                strtoupper($alert->severityLabel()),
                strtoupper($priority),
                $alert->escalationLabel(),
                ucfirst(str_replace('_', ' ', $alert->channel)),
                $alert->triggered_at->format('H:i')
            );
        }
        $body .= "\n".'Event time: '.($alert->event_at?->format('H:i') ?? $alert->triggered_at->format('H:i'));
        if ($alert->credential_reference) {
            $body .= "\n".'Credential reference: '.$alert->credential_reference;
        }
        if ($alert->vehicle_context) {
            $body .= "\n".'Registered vehicles: '.implode('; ', $alert->vehicle_context);
        }
        $body .= "\n".'Status: '.($alert->status === 'acknowledged' ? 'SECURITY RESPONDING' : 'SECURITY ALERTED');

        return [
            'incident_id' => $alert->incident_id,
            'title' => $title,
            'body' => $body,
            'priority' => $priority,
            'incident_type' => $alert->assistance_type,
            'resident_name' => $user->display_name,
            'person' => $user->display_name,
            'capacity' => $capacity,
            'representative_role' => $repAppointment?->role?->label(),
            'owner_entity' => $ownerEntityName,
            'owner_entity_type' => $ownerEntity?->entity_type?->label(),
            'property' => $alert->propertyLabel() ?? $user->propertyLabel(),
            'current_event' => $alert->event_type ? strtoupper(str_replace('_', ' ', $alert->event_type)) : $currentEvent,
            'gate' => $gateLabel,
            'event_at' => $alert->event_at?->toIso8601String(),
            'credential_reference' => $alert->credential_reference,
            'vehicle_context' => $alert->vehicle_context ?? [],
            'credential_status' => $credentialStatus,
            'assistance' => ucfirst($alert->assistance_type),
            'severity' => $alert->severity_level,
            'severity_label' => $alert->severityLabel(),
            'channel' => $alert->channel,
            'escalation_level' => $alert->escalation_level,
            'escalation_label' => $alert->escalationLabel(),
            'status' => in_array($alert->status, ['open', 'acknowledged'], true) ? 'ACTIVE' : $alert->status,
            'status_label' => $alert->status === 'acknowledged' ? 'Security responding' : 'Security alerted',
            'triggered_at' => $alert->triggered_at->toIso8601String(),
        ];
    }

    /**
     * Determine the current event description based on channel and gate.
     */
    private function determineCurrentEvent(string $channel, ?string $gate): string
    {
        if ($gate) {
            return 'Community Entry';
        }

        return match ($channel) {
            'app' => 'In-App Request',
            'credential' => 'Credential Assistance Requested',
            'qr_gesture' => 'QR Gesture Triggered',
            'qr_scan' => 'Credential Scanned',
            'duress_pin' => 'Duress PIN Entered',
            default => 'Assistance Requested',
        };
    }

    /**
     * Determine credential status based on channel.
     */
    private function determineCredentialStatus(string $channel): string
    {
        return match ($channel) {
            'qr_gesture' => 'Armed; gate scan pending',
            'qr_scan', 'duress_pin' => 'Verified',
            'app' => 'App-Based',
            default => 'Unknown',
        };
    }

    /** Persist a fixed snapshot so later profile or alert edits cannot rewrite the timeline. */
    private function incidentSnapshot(DistressAlert $alert): array
    {
        $user = $alert->user;
        $visitor = $alert->visitor;
        $direction = match ($alert->event_type) {
            'entry_attempt' => 'ENTERING',
            'exit_attempt' => 'EXITING',
            default => null,
        };

        $repAppointment = $user?->activeAuthorizedAppointments()->with('ownershipEntity')->first();
        $ownerEntity = $repAppointment?->ownershipEntity;

        return [
            'resident' => $user?->display_name ?? $visitor?->name ?? $alert->gatePass?->holder_name,
            'person' => $user?->display_name ?? $visitor?->name ?? $alert->gatePass?->holder_name,
            'capacity' => $repAppointment ? 'Authorized Representative' : ($user ? $user->role->label() : 'Visitor'),
            'representativeRole' => $repAppointment?->role?->label(),
            'ownerEntity' => $ownerEntity?->legal_name,
            'ownerEntityType' => $ownerEntity?->entity_type?->label(),
            'property' => $alert->propertyLabel() ?? $user?->propertyLabel() ?? $alert->gatePass?->property ?? $visitor?->property?->label() ?? $visitor?->homeowner?->propertyLabel(),
            'credential' => $alert->credential_reference
                ? 'Verified'
                : $this->determineCredentialStatus($alert->channel),
            'credentialReference' => $alert->credential_reference,
            'gate' => $alert->gate
                ? config('gatepass.gates.'.$alert->gate, GateId::tryFrom($alert->gate)?->label() ?? $alert->gate)
                : null,
            'direction' => $direction,
            'assistance' => ucfirst($alert->assistance_type),
            'status' => in_array($alert->status, ['open', 'acknowledged'], true) ? 'ACTIVE' : ucfirst($alert->status),
            'activatedAt' => $alert->triggered_at?->toIso8601String(),
        ];
    }

    private function recordAudit(
        DistressAlert $alert,
        string $eventType,
        ?User $actor = null,
        array $snapshot = [],
    ): DistressAlertEvent {
        return DistressAlertEvent::create([
            'incident_id' => $alert->incident_id,
            'distress_alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'safety_profile_id' => $alert->safety_profile_id,
            'visitor_id' => $alert->visitor_id,
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'occurred_at' => now(),
            'snapshot' => $snapshot,
        ]);
    }

    /** A dead websocket server must not lose the alert: it is already saved and notified. */
    private function broadcast(DistressAlert $alert): void
    {
        rescue(fn () => SilentAssistanceRequested::dispatch($alert->loadMissing('user')), report: true);
    }
}
