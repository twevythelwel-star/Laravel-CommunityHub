<?php

namespace App\Actions\Billing;

use App\Jobs\SendInvoicePaymentReminderJob;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queues the payment reminders that are due today. An outstanding invoice is first
 * reminded three days after its due date, then weekly, and at most three times in all
 * (reminders an administrator sends by hand count too). Scheduled reminders go in-app
 * and by email only; administrators can still choose SMS when reminding by hand.
 */
class QueueDuePaymentRemindersAction
{
    public const FIRST_REMINDER_AFTER_DAYS = 3;

    public const REMIND_EVERY_DAYS = 7;

    public const MAX_REMINDERS = 3;

    public const CHANNELS = ['in_app', 'email'];

    /** @return int how many reminders were queued */
    public function execute(): int
    {
        $queued = 0;

        $this->due()->each(function (Invoice $invoice) use (&$queued) {
            SendInvoicePaymentReminderJob::dispatch($invoice, self::CHANNELS);
            $queued++;
        });

        return $queued;
    }

    /** @return Builder<Invoice> */
    public function due(): Builder
    {
        return Invoice::query()
            ->outstanding()
            ->whereNotNull('user_id')
            ->whereDate('due_on', '<=', now()->subDays(self::FIRST_REMINDER_AFTER_DAYS))
            ->where('reminder_count', '<', self::MAX_REMINDERS)
            ->where(fn (Builder $query) => $query
                ->whereNull('last_reminded_at')
                ->orWhere('last_reminded_at', '<=', now()->subDays(self::REMIND_EVERY_DAYS)));
    }
}
