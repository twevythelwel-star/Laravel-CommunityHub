<?php

namespace App\Actions\Visitors;

use App\Enums\PassCategory;
use App\Enums\VisitorStatus;
use App\Models\BlocklistEntry;
use App\Models\EventInvite;
use App\Models\Visitor;
use App\Services\GatePassEngine;
use App\Services\Messaging\PhoneNumber;
use App\Services\SmsService;
use Illuminate\Validation\ValidationException;

/**
 * A guest answering a resident's public RSVP link: they become an expected
 * visitor of the host for the event's time, get a gate pass, and are texted
 * its link when their contact is a phone number and SMS is configured.
 *
 * Refused (as a form error) once the invite is closed, or when the name is
 * on the blocklist; the blocklist message does not say why.
 */
class RegisterEventGuestAction
{
    public function __construct(
        private readonly GatePassEngine $engine,
        private readonly SmsService $sms,
    ) {}

    /**
     * @param  array{name: string, contact: string, vehicle?: ?string}  $validated
     * @return array{visitor: Visitor, smsSent: bool}
     */
    public function execute(EventInvite $invite, array $validated): array
    {
        if (! $invite->isOpen()) {
            throw ValidationException::withMessages(['general' => 'This RSVP invitation is closed or has expired.']);
        }

        $name = trim($validated['name']);

        if (BlocklistEntry::blocks($name)) {
            throw ValidationException::withMessages([
                'name' => 'Registration cannot be processed at this time. Please contact your host directly.',
            ]);
        }

        $visitor = Visitor::create([
            'homeowner_id' => $invite->host_id,
            'name' => $name,
            'type' => 'Visitor',
            'contact' => $validated['contact'],
            'vehicle' => ! empty($validated['vehicle']) ? strtoupper(trim($validated['vehicle'])) : null,
            'expected_at' => $invite->expected_at,
            'status' => VisitorStatus::Expected,
            'notify_sms' => true,
        ]);

        $this->engine->issueGuestPass($visitor, $invite->host, PassCategory::Visitor);

        return ['visitor' => $visitor, 'smsSent' => $this->textPass($visitor, $invite)];
    }

    /** Best effort: a failed text does not undo the registration. */
    private function textPass(Visitor $visitor, EventInvite $invite): bool
    {
        $phone = PhoneNumber::toE164($visitor->contact);

        if (! $phone || ! $this->sms->isConfigured()) {
            return false;
        }

        try {
            $this->sms->sendVisitorPass(
                $phone,
                $visitor->guestPassUrl(),
                $visitor->name,
                $invite->host->display_name ?? 'Resident Host',
                $invite->expected_at->format('M j, Y g:i A')
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
