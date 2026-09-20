<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Renter extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'status', 'lease_start', 'lease_end', 'lot', 'street'];

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

    public function leaseHasExpired(): bool
    {
        return $this->lease_end->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active')->whereDate('lease_end', '>=', now());
    }
}
