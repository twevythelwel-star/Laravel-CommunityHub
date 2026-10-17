<?php

namespace App\Console\Commands;

use App\Actions\Facilities\QueueFacilityBookingRemindersAction;
use App\Models\FacilityBooking;
use Illuminate\Console\Command;

class SendFacilityBookingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facilities:send-booking-reminders
                            {--dry-run : List the reservations that would be reminded without sending anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue in-app notifications reminding residents of their bookings tomorrow';

    public function handle(QueueFacilityBookingRemindersAction $action): int
    {
        if ($this->option('dry-run')) {
            $due = $action->due()->with(['landmark', 'user'])->get();
            $this->table(['Reference', 'Facility', 'Resident', 'Date', 'Slot', 'Fee'], $due->map(fn (FacilityBooking $b) => [
                $b->reference,
                $b->landmark?->name ?? 'Unknown',
                $b->user?->name ?? 'Unknown',
                $b->booked_on->toDateString(),
                $b->slotLabel(),
                $b->feeFormatted(),
            ]));
            $this->comment("{$due->count()} reservation reminder(s) would be queued.");

            return Command::SUCCESS;
        }

        $count = $action->execute();
        $this->info("{$count} facility booking reminder(s) queued.");

        return Command::SUCCESS;
    }
}
