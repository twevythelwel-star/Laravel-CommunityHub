<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A community notice or announcement: the one record the notice board, the
 * dashboard home and the residents' feed all read.
 *
 * Its audience is target_type + target_criteria (street, block, section,
 * custom group or roles); target_roles carries the roles chosen by the AI
 * targeting flow. Each resident it was sent to has an AnnouncementRecipient
 * row recording the channels tried and what each answered.
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id',
        'title',
        'content',
        'category',
        'priority',
        'author_id',
        'author_name',
        'target_roles',
        'target_type',
        'target_criteria',
        'channels',
        'status',
        'scheduled_for',
        'sent_at',
        'cancelled_at',
        'approved_by',
        'approved_at',
        'stats',
        'published_at',
        'expires_at',
        'requires_acknowledgement',
        'acknowledgement_deadline',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'approved_at' => 'datetime',
            'acknowledgement_deadline' => 'datetime',
            'requires_acknowledgement' => 'boolean',
            'target_roles' => 'array',
            'target_criteria' => 'array',
            'channels' => 'array',
            'stats' => 'array',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'notification_id');
    }

    /** Who it was sent to, one row per resident, with each channel's outcome. */
    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    /**
     * This resident's recipient row, created on first contact for a notice
     * they reached in-app before (or without) a dispatch run recording them.
     */
    public function recipientRecordFor(User $user): AnnouncementRecipient
    {
        return $this->recipients()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'recipient_name' => $user->display_name ?: $user->name,
                'recipient_email' => $user->email,
                'recipient_phone' => $user->phone,
                'delivery_state' => 'delivered',
                'attempted_channels' => ['in_app'],
                'delivered_at' => now(),
            ],
        );
    }

    /** Mark it read by this resident, on the board and in their in-app notification centre. */
    public function markReadBy(User $user): AnnouncementRecipient
    {
        $recipient = $this->recipientRecordFor($user)->markAsRead();

        InAppNotification::where('user_id', $user->id)
            ->whereJsonContains('data->announcement_id', $this->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $recipient;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPendingOrScheduled(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval', 'scheduled'], true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeForCommunity($query, ?int $communityId)
    {
        if (! $communityId) {
            return $query;
        }

        return $query->where(function ($q) use ($communityId) {
            $q->where('community_id', $communityId)
                ->orWhereNull('community_id');
        });
    }

    /** Null or empty target_roles means the notice goes to everyone. */
    public function targetsRole(string $role): bool
    {
        return blank($this->target_roles) || in_array($role, $this->target_roles, true);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_roles')
                ->orWhereJsonContains('target_roles', $role);
        });
    }
}
