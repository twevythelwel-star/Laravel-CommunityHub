<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'reference', 'stripe_session_id', 'stripe_payment_intent',
        'amount_minor', 'currency', 'period_start', 'period_end', 'due_on', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_on' => 'date',
            'paid_at' => 'datetime',
            'amount_minor' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amount(): float
    {
        return (float) ($this->amount_minor / 100);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'Unpaid' && $this->due_on->isPast();
    }

    public function markPaid(): void
    {
        $this->update(['status' => 'Paid', 'paid_at' => now()]);
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['Unpaid', 'Overdue']);
    }
}
