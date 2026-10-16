<?php

namespace App\Actions\Communications;

use App\Enums\UserRole;
use App\Models\Community;
use App\Models\Notification;
use App\Models\User;
use App\Services\Communications\AudienceResolutionService;
use App\Services\NotificationEngine\NotificationEngine;
use Illuminate\Support\Carbon;

class PublishAnnouncementAction
{
    public function __construct(
        private readonly AudienceResolutionService $resolver,
        private readonly NotificationEngine $engine
    ) {}

    public function execute(User $user, array $validated, ?array $roles, array $channels): Notification
    {
        $communityId = $validated['community_id'] ?? $user->community_id;
        $community = $communityId ? Community::find($communityId) : Community::query()->where('code', config('gatepass.default_community_id'))->first();

        // 1. Timezone-aware scheduling & expiry in community's configured timezone
        $communityTz = $community?->timezone ?? config('app.timezone', 'America/Jamaica');
        $scheduledFor = $validated['scheduled_for'] ?? null;
        $parsedSchedule = filled($scheduledFor)
            ? Carbon::parse($scheduledFor, $communityTz)->setTimezone('UTC')
            : null;
        $isScheduled = $parsedSchedule !== null && $parsedSchedule->isFuture();

        $expiresAt = $validated['expires_at'] ?? null;
        $parsedExpiresAt = filled($expiresAt)
            ? Carbon::parse($expiresAt, $communityTz)->setTimezone('UTC')
            : null;

        // 2. Content safety: sanitize untrusted HTML & strip unsafe link protocols
        $sanitizedContent = strip_tags($validated['content'], '<p><br><strong><em><ul><ol><li><a>');
        $sanitizedContent = preg_replace('/href=[\'"]\s*(javascript|data|vbscript):[^\'"]*[\'"]/i', 'href="#"', $sanitizedContent);

        // 3. Duplicate prevention: the same author posting the same title twice
        // within 30 seconds (a double-click or a retried request) gets the first.
        $existingDuplicate = Notification::query()
            ->where('community_id', $community?->id)
            ->where('author_id', $user->id)
            ->where('title', $validated['title'])
            ->where('created_at', '>=', now()->subSeconds(30))
            ->latest('id')
            ->first();

        if ($existingDuplicate) {
            return $existingDuplicate;
        }

        $targetType = $validated['target_type'] ?? ($roles !== null ? 'roles' : 'entire_community');

        $announcement = Notification::create([
            'community_id' => $community?->id,
            'title' => $validated['title'],
            'content' => $sanitizedContent,
            'category' => $validated['category'] ?? 'general',
            'priority' => $validated['priority'] ?? 'normal',
            'target_roles' => $roles,
            'target_type' => $targetType,
            'target_criteria' => $validated['target_criteria'] ?? null,
            'channels' => $channels,
            'status' => $isScheduled ? 'scheduled' : 'published',
            'scheduled_for' => $isScheduled ? $parsedSchedule : null,
            'expires_at' => $parsedExpiresAt,
            'author_id' => $user->id,
            'author_name' => $user->display_name,
            'published_at' => $isScheduled ? $parsedSchedule : now(),
            'sent_at' => $isScheduled ? null : now(),
        ]);

        // 4. Audit logging & urgent message handling
        $communityName = $community?->name ?? config('app.name');
        if (($validated['category'] ?? '') === 'emergency' || ($validated['priority'] ?? '') === 'critical') {
            $user->recordActivity("Emergency broadcast dispatched: '{$announcement->title}' (Priority: {$announcement->priority})");
        } elseif ($user->role === UserRole::SystemAdmin) {
            $user->recordActivity("System Admin published broadcast '#{$announcement->id}' for community '{$communityName}' (Target: {$targetType})");
        } else {
            $user->recordActivity($isScheduled ? "Scheduled notice: {$announcement->title}" : "Published notice: {$announcement->title}");
        }

        if (! $isScheduled) {
            $this->resolver->dispatchInBatches($community ?? Community::first(), $announcement, $this->engine);
        }

        return $announcement;
    }
}
