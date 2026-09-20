<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'name', 'job', 'id_type', 'id_number', 'id_expiry', 'id_image_url',
        'property', 'added_by', 'status', 'photo_url',
    ];

    protected function casts(): array
    {
        return ['id_expiry' => 'date'];
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** Identity documents past their expiry force the Expired ID status. */
    public function hasExpiredId(): bool
    {
        return $this->id_expiry !== null && $this->id_expiry->isPast();
    }

    public function effectiveStatus(): string
    {
        return $this->hasExpiredId() ? 'Expired ID' : $this->status;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
