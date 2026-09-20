<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Amounts are held in minor units (cents) so totals never drift through float
 * arithmetic — the original front end summed plain JS numbers.
 */
class Fundraiser extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'goal_minor', 'goal_currency',
        'start_date', 'end_date', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'goal_minor' => 'integer',
        ];
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function goal(): float
    {
        return (float) ($this->goal_minor / 100);
    }

    /** Total raised, in minor units. */
    public function raisedMinor(): int
    {
        return (int) $this->donations()->sum('amount_minor');
    }

    public function raised(): float
    {
        return (float) ($this->raisedMinor() / 100);
    }

    public function progressPercent(): float
    {
        if ($this->goal_minor <= 0) {
            return 0.0;
        }

        return round(min(100, ($this->raisedMinor() / $this->goal_minor) * 100), 1);
    }

    public function donorCount(): int
    {
        return $this->donations()->count();
    }

    public function isOpen(): bool
    {
        return $this->status === 'Active' && $this->end_date->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
