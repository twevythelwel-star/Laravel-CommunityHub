<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Amenity extends Model
{
    use HasFactory;

    protected $fillable = ['landmark_id', 'name', 'category', 'max_guests', 'opens_at', 'closes_at', 'is_active'];

    protected function casts(): array
    {
        return ['max_guests' => 'integer', 'is_active' => 'boolean'];
    }

    public function landmark(): BelongsTo
    {
        return $this->belongsTo(Landmark::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AmenityBooking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Whether a slot starts and ends inside this amenity's opening hours. */
    public function isOpenFor(string $slot): bool
    {
        $window = AmenityBooking::SLOTS[$slot] ?? null;

        return $window !== null
            && $window['starts'] >= substr((string) $this->opens_at, 0, 5)
            && $window['ends'] <= substr((string) $this->closes_at, 0, 5);
    }

    /** Opening hours as the booking dialog shows them, e.g. "08:00 - 22:00". */
    public function openHoursLabel(): string
    {
        return substr((string) $this->opens_at, 0, 5).' - '.substr((string) $this->closes_at, 0, 5);
    }
}
