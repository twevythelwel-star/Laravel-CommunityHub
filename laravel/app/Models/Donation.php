<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'fundraiser_id', 'user_id', 'amount_minor', 'currency',
        'donor_name', 'is_anonymous', 'donated_at',
    ];

    protected function casts(): array
    {
        return [
            'donated_at' => 'datetime',
            'is_anonymous' => 'boolean',
            'amount_minor' => 'integer',
        ];
    }

    public function fundraiser(): BelongsTo
    {
        return $this->belongsTo(Fundraiser::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amount(): float
    {
        return (float) ($this->amount_minor / 100);
    }

    /** Never leak a donor identity that was marked anonymous. */
    public function publicDonorName(): string
    {
        return $this->is_anonymous ? 'Anonymous' : ($this->donor_name ?? 'Anonymous');
    }
}
