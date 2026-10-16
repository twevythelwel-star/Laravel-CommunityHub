<?php

namespace App\Console\Commands;

use App\Jobs\DispatchAnnouncementJob;
use App\Models\Notification as AnnouncementModel;
use Illuminate\Console\Command;

class DispatchScheduledAnnouncementsCommand extends Command
{
    protected $signature = 'announcements:dispatch-scheduled';

    protected $description = 'Dispatch due scheduled community announcements';

    public function handle(): int
    {
        $due = AnnouncementModel::query()
            ->scheduled()
            ->where('scheduled_for', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled announcements are currently due.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($due as $announcement) {
            $announcement->update(['status' => 'queued']);
            DispatchAnnouncementJob::dispatch($announcement);
            $count++;
            $this->line("Queued announcement #{$announcement->id}: '{$announcement->title}'");
        }

        $this->info("Successfully dispatched {$count} scheduled announcement(s).");

        return self::SUCCESS;
    }
}
