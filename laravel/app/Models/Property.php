<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id',
        'owner_user_id',
        'property_code',
        'lot_number',
        'street_address',
        'property_type',
        'status',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** "Lot 14, Hibiscus Way": how the property reads on an invoice or a pass. */
    public function label(): string
    {
        return collect([$this->lot_number, $this->street_address])->filter()->implode(', ');
    }
}
