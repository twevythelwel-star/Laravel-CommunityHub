<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AmenityBooking extends Model
{
    use HasFactory;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The bookable windows, as 24-hour HH:MM. Times compare correctly as
     * strings because they are zero-padded.
     *
     * @var array<string, array{starts: string, ends: string}>
     */
    public const SLOTS = [
        'morning' => ['starts' => '08:00', 'ends' => '12:00'],
        'afternoon' => ['starts' => '12:00', 'ends' => '16:00'],
        'evening' => ['starts' => '16:00', 'ends' => '20:00'],
        'night' => ['starts' => '20:00', 'ends' => '22:00'],
    ];

    protected $fillable = ['reference', 'amenity_id', 'user_id', 'booked_on', 'slot', 'guests', 'notes', 'status', 'slot_key', 'cancelled_at', 'cancelled_by', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['booked_on' => 'date', 'guests' => 'integer', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking): void {
            $booking->reference ??= 'BK-'.now()->format('Y').'-'.strtoupper(Str::random(8));
            $booking->status ??= self::STATUS_CONFIRMED;
            $booking->slot_key = $booking->status === self::STATUS_CONFIRMED
                ? self::slotKey($booking->amenity_id, $booking->booked_on->toDateString(), $booking->slot)
                : null;
        });
    }

    public static function slotKey(int $amenityId, string $date, string $slot): string
    {
        return "{$amenityId}:{$date}:{$slot}";
    }

    public function amenity(): BelongsTo
    {
        return $this->belongsTo(Amenity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /** Frees the slot for someone else while keeping the booking on record. */
    public function cancel(?User $by = null, ?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'slot_key' => null,
            'cancelled_at' => now(),
            'cancelled_by' => $by?->id,
            'cancellation_reason' => filled($reason) ? trim($reason) : null,
        ]);
    }

    /** Cancelled by the office or an administrator, not by the resident who booked it. */
    public function wasCancelledByOthers(): bool
    {
        return $this->status === self::STATUS_CANCELLED
            && $this->cancelled_by !== null
            && $this->cancelled_by !== $this->user_id;
    }
}
