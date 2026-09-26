<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentQrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_type',
        'payment_link_id',
        'transaction_id',
        'token',
        'target_url',
        'qr_data_uri',
        'scan_count',
        'expires_at',
        'active',
    ];

    protected $casts = [
        'scan_count' => 'integer',
        'expires_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
