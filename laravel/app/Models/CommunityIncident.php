<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityIncident extends Model
{
    use HasFactory;

    public const STATUS_REPORTED = 'reported';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CONTAINED = 'contained';
    public const STATUS_ALL_CLEAR = 'all_clear';
    public const STATUS_CLOSED = 'closed';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_FIRE = 'fire';
    public const CATEGORY_MEDICAL = 'medical';
    public const CATEGORY_UTILITY_OUTAGE = 'utility_outage';
    public const CATEGORY_SEVERE_WEATHER = 'severe_weather';
    public const CATEGORY_INFRASTRUCTURE = 'infrastructure';
    public const CATEGORY_GENERAL = 'general';

    protected $fillable = [
        'incident_number',
        'title',
        'category',
        'severity',
        'status',
        'location',
        'summary',
        'sensitive_details',
        'reported_by',
        'all_clear_at',
        'all_clear_by',
        'all_clear_message',
        'broadcast_sent',
    ];

    protected function casts(): array
    {
        return [
            'all_clear_at' => 'datetime',
            'broadcast_sent' => 'boolean',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function allClearUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'all_clear_by');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(CommunityIncidentUpdate::class, 'incident_id')->latest();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_REPORTED,
            self::STATUS_INVESTIGATING,
            self::STATUS_ACTIVE,
            self::STATUS_CONTAINED,
        ]);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_ALL_CLEAR,
            self::STATUS_CLOSED,
        ]);
    }

    public function isAllClear(): bool
    {
        return $this->status === self::STATUS_ALL_CLEAR;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_REPORTED,
            self::STATUS_INVESTIGATING,
            self::STATUS_ACTIVE,
            self::STATUS_CONTAINED,
        ], true);
    }

    /**
     * Generate the next sequential incident number formatted like INC-YYYY-XXXX.
     */
    public static function generateIncidentNumber(): string
    {
        $year = date('Y');
        $latest = static::where('incident_number', 'LIKE', "INC-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $sequence = 1;
        if ($latest && preg_match("/INC-{$year}-(\d+)/", $latest->incident_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('INC-%s-%04d', $year, $sequence);
    }
}
