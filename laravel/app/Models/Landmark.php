<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Landmark extends Model
{
    use HasFactory;

    protected $fillable = ['community_id', 'name', 'category', 'description', 'lat', 'lng', 'icon', 'created_by'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Pin colour per category, matching LANDMARK_PIN_CONFIGS on the client. */
    public function pinColor(): string
    {
        return match ($this->category) {
            'Security Gate' => '#10B981',
            'Community Center' => '#EF4444',
            'Park' => '#3B82F6',
            default => '#64748B',
        };
    }

    /** The chosen icon, else the category's. */
    public function iconType(): string
    {
        return $this->icon ?? match ($this->category) {
            'Community Center' => 'building',
            'Park' => 'trees',
            default => 'shield',
        };
    }
}
