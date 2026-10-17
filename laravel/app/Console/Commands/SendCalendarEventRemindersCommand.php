<?php

namespace App\Console\Commands;

use App\Actions\Calendar\QueueEventRemindersAction;
use App\Models\CommunityEvent;
use Illuminate\Console\Command;

class SendCalendarEventRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:send-event-reminders
                            {--dry-run : List the upcoming events that would be reminded without dispatching notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue in-app notifications reminding residents of calendar events scheduled in the next 24 hours';

    public function handle(QueueEventRemindersAction $action): int
    {
        if ($this->option('dry-run')) {
            $due = $action->due();
            $this->table(['Title', 'Type', 'Schedule', 'Location'], $due->map(fn (CommunityEvent $e) => [
                $e->title,
                $e->typeLabel(),
                $e->formattedSchedule(),
                $e->location ?? 'N/A',
            ]));
            $this->comment("{$due->count()} event reminder(s) would be queued.");

            return Command::SUCCESS;
        }

        $count = $action->execute();
        $this->info("{$count} calendar event reminder(s) queued.");

        return Command::SUCCESS;
    }
}
