<?php

namespace App\Console\Commands;

use App\Actions\Billing\QueueDuePaymentRemindersAction;
use Illuminate\Console\Command;

class SendDuePaymentRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:send-payment-reminders
                            {--dry-run : List the invoices that would be reminded without sending anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue in-app and email reminders for overdue invoices (3 days after due, then weekly, at most 3)';

    /**
     * Execute the console command.
     */
    public function handle(QueueDuePaymentRemindersAction $reminders): int
    {
        if ($this->option('dry-run')) {
            $due = $reminders->due()->get(['id', 'reference', 'due_on', 'reminder_count']);
            $this->table(['Invoice', 'Due', 'Reminders so far'], $due->map(fn ($invoice) => [
                $invoice->reference, $invoice->due_on?->toDateString(), $invoice->reminder_count,
            ]));
            $this->comment("{$due->count()} reminder(s) would be queued.");

            return Command::SUCCESS;
        }

        $this->info($reminders->execute().' payment reminder(s) queued.');

        return Command::SUCCESS;
    }
}
