<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\VerifyTwilioSignature;
use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Twilio reports whether a receipt SMS was delivered. Only callbacks it
 * signed are accepted, and a delivery update never changes what the payment
 * ledger says about the payment itself.
 */
class TwilioStatusWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/webhooks/twilio/status';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.twilio.token' => 'twilio-auth-token']);
    }

    /** @param  array<string, string>  $fields */
    private function statusCallback(array $fields, ?string $signature = null): TestResponse
    {
        $signature ??= VerifyTwilioSignature::sign(url(self::URL), $fields, 'twilio-auth-token');

        return $this->post(self::URL, $fields, ['X-Twilio-Signature' => $signature]);
    }

    private function sentReceipt(): Transaction
    {
        $transaction = Transaction::create([
            'user_id' => User::factory()->role(UserRole::Homeowner)->create()->id,
            'amount_minor' => 1500000, 'currency' => 'JMD', 'payment_channel' => 'bank_wire',
            'reference' => 'TX-SMS-1', 'status' => 'completed',
        ]);
        TransactionEvent::create([
            'transaction_id' => $transaction->id, 'event_type' => 'receipt_sms_sent',
            'provider_event_id' => 'SM123', 'status' => 'completed', 'occurred_at' => now(),
        ]);

        return $transaction;
    }

    public function test_a_signed_delivery_update_is_recorded_without_touching_the_payment(): void
    {
        $transaction = $this->sentReceipt();

        $this->statusCallback(['MessageSid' => 'SM123', 'MessageStatus' => 'undelivered', 'ErrorCode' => '30003', 'ErrorMessage' => 'Unreachable'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $update = TransactionEvent::where('event_type', 'twilio_delivery_status')->sole();
        $this->assertSame('completed', $update->status);
        $this->assertSame('Twilio delivery update: undelivered (Error 30003: Unreachable)', $update->payload_reference);
        $this->assertSame('completed', $transaction->fresh()->status);
    }

    public function test_an_unsigned_or_forged_callback_is_refused(): void
    {
        $this->sentReceipt();

        $this->post(self::URL, ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'])->assertForbidden();
        $this->statusCallback(['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'], 'forged')->assertForbidden();
        $this->statusCallback(['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'], VerifyTwilioSignature::sign(url(self::URL), ['MessageSid' => 'SM123', 'MessageStatus' => 'failed'], 'twilio-auth-token'))->assertForbidden();

        $this->assertSame(0, TransactionEvent::where('event_type', 'twilio_delivery_status')->count());
    }

    public function test_without_an_auth_token_nothing_is_trusted(): void
    {
        config(['services.twilio.token' => '']);

        $this->statusCallback(['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'], 'anything')->assertForbidden();
    }
}
