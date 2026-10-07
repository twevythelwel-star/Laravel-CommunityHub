<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Renter extends Model
{
    use HasFactory;

    protected $fillable = [
        'homeowner_id',
        'property_id',
        'user_id',
        'name',
        'stay_type',
        'contact',
        'notes',
        'status',
        'lease_start',
        'lease_end',
        'lot',
        'street',
    ];

    protected function casts(): array
    {
        return [
            'lease_start' => 'date',
            'lease_end' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function homeowner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeowner_id');
    }

    /** The property the stay is for; an owner may hold several. */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leaseHasExpired(): bool
    {
        return $this->lease_end->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active')->whereDate('lease_end', '>=', now()->toDateString());
    }
}
