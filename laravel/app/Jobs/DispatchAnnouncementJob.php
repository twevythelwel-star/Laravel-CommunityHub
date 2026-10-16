<?php

namespace App\Jobs;

use App\Events\Communications\AnnouncementDispatched;
use App\Models\Community;
use App\Models\Notification as AnnouncementModel;
use App\Services\Communications\AudienceResolutionService;
use App\Services\NotificationEngine\NotificationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public AnnouncementModel $announcement
    ) {}

    public function handle(AudienceResolutionService $resolver, NotificationEngine $engine): void
    {
        $announcement = $this->announcement->fresh(['community', 'author']);

        if (! $announcement || $announcement->isCancelled()) {
            Log::info("[DispatchAnnouncementJob] Announcement #{$this->announcement->id} was cancelled or missing; skipping.");

            return;
        }

        $community = $announcement->community ?? Community::query()
            ->where('code', config('gatepass.default_community_id'))
            ->first() ?? Community::first();

        if (! $community) {
            Log::error("[DispatchAnnouncementJob] No valid community found for announcement #{$announcement->id}.");

            return;
        }

        $announcement->update(['status' => 'dispatching']);

        $stats = $resolver->dispatchInBatches($community, $announcement, $engine);

        event(new AnnouncementDispatched($announcement, $stats));
    }
}
