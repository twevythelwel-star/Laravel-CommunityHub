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
        'confirms_entry_id',
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

    public function scopeDenied($query)
    {
        return $query->where('result', 'DENY');
    }
}
