<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A resident's booking of a community facility (a bookable landmark) for one time slot
 * on one day. Free and confirmed at once; a slot holds one confirmed booking.
 */
class FacilityBooking extends Model
{
    /** Slot key => [starts, ends], matching the booking dialog. */
    public const SLOTS = [
        'morning' => ['08:00', '12:00'],
        'afternoon' => ['12:00', '16:00'],
        'evening' => ['16:00', '20:00'],
        'night' => ['20:00', '22:00'],
    ];

    /** Guests allowed when a facility has no capacity set. */
    public const DEFAULT_CAPACITY = 50;

    protected $fillable = [
        'reference',
        'landmark_id',
        'user_id',
        'booked_on',
        'slot',
        'guests',
        'notes',
        'status',
        'cancelled_at',
        'cancelled_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'booked_on' => 'date',
            'guests' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function landmark(): BelongsTo
    {
        return $this->belongsTo(Landmark::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('booked_on', '>=', today());
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'BK-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function slotLabel(): string
    {
        [$starts, $ends] = self::SLOTS[$this->slot] ?? ['?', '?'];

        return "{$starts}–{$ends}";
    }
}
