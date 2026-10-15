<?php

namespace App\Services\Messaging;

use App\Models\TransactionEvent;

/**
 * Records Twilio's delivery status for a payment receipt sent by SMS or
 * WhatsApp against that payment's event history.
 *
 * Twilio never says whether a payment succeeded: the ledger does. So the
 * recorded event carries the payment's own status unchanged, and the
 * delivery outcome only in its description.
 */
class ReceiptDeliveryLog
{
    /** @return bool Whether the message belonged to a payment receipt. */
    public function record(string $messageSid, string $status, ?string $errorCode = null, ?string $errorMessage = null): bool
    {
        $transaction = TransactionEvent::where('provider_event_id', $messageSid)->latest()->first()?->transaction;

        if (! $transaction) {
            return false;
        }

        TransactionEvent::create([
            'transaction_id' => $transaction->id,
            'event_type' => 'twilio_delivery_status',
            'provider_event_id' => $messageSid,
            'status' => $transaction->status,
            'payload_reference' => sprintf(
                'Twilio delivery update: %s%s',
                $status,
                $errorCode ? " (Error {$errorCode}: {$errorMessage})" : ''
            ),
            'occurred_at' => now(),
            'processed_at' => now(),
        ]);

        return true;
    }
}
