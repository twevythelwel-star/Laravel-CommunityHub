<?php

namespace App\Http\Controllers;

use App\Services\Messaging\ReceiptDeliveryLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Handles incoming Twilio messaging status callbacks.
 *
 * CRITICAL ARCHITECTURAL INVARIANT:
 * Twilio NEVER tells CommunityHub whether a payment succeeded.
 * The payment gateway / authoritative ledger establishes transaction truth (PAID).
 * CommunityHub then instructs Twilio to dispatch SMS/WhatsApp receipts.
 * This webhook tracks ONLY the delivery status of those messages
 * (e.g. queued -> sent -> delivered / undelivered / failed); see
 * ReceiptDeliveryLog.
 *
 * The route carries `twilio.signature` (VerifyTwilioSignature), so only
 * callbacks Twilio signed reach this controller.
 */
class TwilioWebhookController extends Controller
{
    public function messagingStatus(Request $request, ReceiptDeliveryLog $deliveries): Response
    {
        $messageSid = (string) $request->input('MessageSid');

        if ($messageSid !== '') {
            $status = (string) $request->input('MessageStatus', 'unknown');

            Log::info("Twilio messaging status callback: {$messageSid} -> {$status}", [
                'to' => (string) $request->input('To', ''),
                'status' => $status,
                'error_code' => $request->input('ErrorCode'),
            ]);

            $deliveries->record($messageSid, $status, $request->input('ErrorCode'), $request->input('ErrorMessage'));
        }

        // Twilio expects TwiML back, even an empty one.
        return response('<Response></Response>', 200, ['Content-Type' => 'text/xml']);
    }
}
