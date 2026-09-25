<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invoice_id',
        'invoice_item_id',
        'payment_link_id',
        'fundraiser_id',
        'donation_id',
        'invoice_item_ids',
        'amount_minor',
        'fee_minor',
        'net_amount_minor',
        'currency',
        'payment_channel',
        'reference',
        'status',
        'reviewed_by',
        'reviewed_at',
        'settled_at',
        'payout_reference',
        'dispute_reason',
        'receipt_number',
        'proof_url',
        'notes',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'fee_minor' => 'integer',
        'net_amount_minor' => 'integer',
        'settled_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'invoice_item_ids' => 'array',
    ];

    /** Recorded for a payment the office must confirm; see PaymentOrchestratorService. */
    public const STATUS_PENDING = 'pending';

    protected static function booted(): void
    {
        static::creating(function (self $tx) {
            $tx->reference ??= 'TX-'.strtoupper(Str::random(10));
            $tx->receipt_number ??= 'REC-'.date('Ymd').'-'.strtoupper(Str::random(6));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    public function fundraiser(): BelongsTo
    {
        return $this->belongsTo(Fundraiser::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function formattedAmount(): string
    {
        return '$'.number_format($this->amount_minor / 100, 2).' '.$this->currency;
    }
}
