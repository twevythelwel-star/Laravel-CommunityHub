<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One resident an announcement was sent to: the contact details used, the
 * channels tried, what each answered, and the overall delivery state. One
 * row per (notification, user), which is what stops duplicate deliveries.
 */
class AnnouncementRecipient extends Model
{
    use HasFactory;

    protected $table = 'announcement_recipients';

    protected $fillable = [
        'notification_id',
        'user_id',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'delivery_state',
        'attempted_channels',
        'channel_outcomes',
        'failure_details',
        'delivered_at',
        'read_at',
        'acknowledged_at',
        'acknowledgement_note',
    ];

    protected function casts(): array
    {
        return [
            'attempted_channels' => 'array',
            'channel_outcomes' => 'array',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function markAsRead(): self
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }

        return $this;
    }

    public function acknowledge(?string $note = null): self
    {
        $this->update([
            'read_at' => $this->read_at ?? now(),
            'acknowledged_at' => now(),
            'acknowledgement_note' => $note,
        ]);

        return $this;
    }
}
