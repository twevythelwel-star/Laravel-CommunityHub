<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function paymentPlan(): HasOne
    {
        return $this->hasOne(PaymentPlan::class);
    }

    public function amount(): float
    {
        return (float) ($this->amount_minor / 100);
    }

    /** Money received less money refunded, from the ledger. */
    public function amountPaidMinor(): int
    {
        $received = (int) $this->transactions()->where('status', 'completed')->sum('amount_minor');
        $refunded = (int) $this->transactions()->where('status', 'refunded')->sum('amount_minor');

        return max(0, $received - $refunded);
    }

    public function balanceRemainingMinor(): int
    {
        return max(0, $this->amount_minor - $this->amountPaidMinor());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['Unpaid', 'Partially Paid']) && $this->due_on->isPast();
    }

    public function markPaid(): void
    {
        $this->update(['status' => 'Paid', 'paid_at' => now()]);
        $this->items()->update(['status' => 'Paid']);
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['Unpaid', 'Overdue', 'Partially Paid']);
    }
}
