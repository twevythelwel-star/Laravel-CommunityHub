<?php

namespace App\Events\Communications;

use App\Models\Notification as AnnouncementModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementDispatched
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AnnouncementModel $announcement,
        public array $stats = []
    ) {}
}
