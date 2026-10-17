<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityEvent extends Model
{
    use HasFactory;

    public const TYPE_EVENT = 'event';

    public const TYPE_PUBLIC_HOLIDAY = 'public_holiday';

    public const TYPE_OFFICE_CLOSURE = 'office_closure';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_MAINTENANCE_WINDOW = 'maintenance_window';

    public const TYPE_SCHEDULED_NOTICE = 'scheduled_notice';

    public const TYPES = [
        self::TYPE_EVENT => 'Community Event',
        self::TYPE_PUBLIC_HOLIDAY => 'Public Holiday',
        self::TYPE_OFFICE_CLOSURE => 'Office Closure',
        self::TYPE_MEETING => 'Meeting',
        self::TYPE_MAINTENANCE_WINDOW => 'Maintenance Window',
        self::TYPE_SCHEDULED_NOTICE => 'Scheduled Notice',
    ];

    protected $fillable = [
        'title',
        'description',
        'event_type',
        'location',
        'is_all_day',
        'remind_before_hours',
        'reminded_at',
        'change_summary',
        'start_date',
        'end_date',
        'image_url',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_all_day' => 'boolean',
            'remind_before_hours' => 'integer',
            'reminded_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->event_type] ?? 'Event';
    }

    public function formattedSchedule(): string
    {
        $start = $this->start_date->format('D j M Y'.($this->is_all_day ? '' : ' g:i A'));
        if (! $this->end_date) {
            return $start;
        }

        $end = $this->start_date->isSameDay($this->end_date)
            ? $this->end_date->format('g:i A')
            : $this->end_date->format('D j M Y g:i A');

        return "{$start} – {$end}";
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_date', '>=', now())->orderBy('start_date');
    }

    /** The longest reminder window an event can ask for (see the form requests). */
    public const MAX_REMIND_BEFORE_HOURS = 168;

    /**
     * Events not yet reminded that start within the longest reminder window; each one's
     * own window is then checked with isWithinReminderWindow().
     */
    public function scopeDueForReminder(Builder $query): Builder
    {
        return $query->whereNull('reminded_at')
            ->where('start_date', '>', now())
            ->where('start_date', '<=', now()->addHours(self::MAX_REMIND_BEFORE_HOURS));
    }

    public function isWithinReminderWindow(): bool
    {
        return $this->start_date->lte(now()->addHours($this->remind_before_hours ?? 24));
    }
}
