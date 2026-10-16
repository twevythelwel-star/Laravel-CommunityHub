<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Community;
use App\Models\Notification as AnnouncementModel;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, AnnouncementModel $announcement): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($announcement->isPublished()) {
            return true;
        }

        // Unpublished or scheduled drafts can only be seen by authorized admins
        return $this->manage($user, $announcement);
    }

    public function create(User $user, ?Community $community = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->role === UserRole::SystemAdmin) {
            return true;
        }

        if ($user->role === UserRole::Admin) {
            if ($community === null) {
                return true;
            }

            // Community Admin cannot broadcast to another community
            return $user->community_id === null || $user->community_id === $community->id;
        }

        return false;
    }

    public function manage(User $user, AnnouncementModel $announcement): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->role === UserRole::SystemAdmin) {
            return true;
        }

        if ($user->role === UserRole::Admin) {
            if ($announcement->community_id === null) {
                return true;
            }

            return $user->community_id === null || $user->community_id === $announcement->community_id;
        }

        return false;
    }

    public function cancel(User $user, AnnouncementModel $announcement): bool
    {
        return $this->manage($user, $announcement) && $announcement->isPendingOrScheduled();
    }
}
