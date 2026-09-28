<?php

namespace App\Models;

use App\Events\AccessLogRecorded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccessLogEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'method', 'gate', 'pass_id',
        'result', 'deny_reason', 'validation_report', 'scanned_by', 'occurred_at',
        'confirms_entry_id', 'confirm_by',
    ];

    protected static function booted(): void
    {
        static::created(function (self $entry): void {
            event(new AccessLogRecorded($entry));
        });
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'confirm_by' => 'datetime',
            'validation_report' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    /** The scan entry this decision settles, when this is a guard's decision. */
    public function confirms(): BelongsTo
    {
        return $this->belongsTo(self::class, 'confirms_entry_id');
    }

    /** The guard's decision on this scan, once made. */
    public function confirmation(): HasOne
    {
        return $this->hasOne(self::class, 'confirms_entry_id');
    }

    /**
     * For a scan with no decision logged against it: 'pending' while the guard
     * can still confirm it, 'expired' once that window has passed (the pass
     * was not used), and null for entries that never needed a decision.
     */
    public function decisionWindow(): ?string
    {
        if ($this->confirm_by === null) {
            return null;
        }

        return $this->confirm_by->isPast() ? 'expired' : 'pending';
    }

    public function scopeDenied($query)
    {
        return $query->where('result', 'DENY');
    }
}
