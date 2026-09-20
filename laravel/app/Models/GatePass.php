<?php

namespace App\Models;

use App\Enums\GateId;
use App\Enums\PassCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pass registry. The original engine held revocation in a hardcoded
 * REVOKED_PASS_IDS Set; revocation is now a persisted, auditable column.
 */
class GatePass extends Model
{
    use HasFactory;

    protected $fillable = [
        'pass_id', 'user_id', 'category', 'holder_name', 'property',
        'access_zone', 'designated_gate', 'color_variant', 'rotation_seq',
        'status', 'revoked_at', 'revoked_by', 'revocation_reason', 'last_rotated_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => PassCategory::class,
            'designated_gate' => GateId::class,
            'revoked_at' => 'datetime',
            'last_rotated_at' => 'datetime',
            'rotation_seq' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isRevoked(): bool
    {
        return $this->status === 'Revoked' || $this->revoked_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'Active' && ! $this->isRevoked();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active')->whereNull('revoked_at');
    }

    public function revoke(?User $by = null, ?string $reason = null): void
    {
        $this->update([
            'status' => 'Revoked',
            'revoked_at' => now(),
            'revoked_by' => $by?->id,
            'revocation_reason' => $reason,
        ]);
    }
}
