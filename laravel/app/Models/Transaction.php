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
        'amount_minor',
        'currency',
        'payment_channel',
        'reference',
        'status',
        'receipt_number',
        'proof_url',
        'notes',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
    ];

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

    public function formattedAmount(): string
    {
        return '$'.number_format($this->amount_minor / 100, 2).' '.$this->currency;
    }
}
