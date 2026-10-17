<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityIncidentUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'user_id',
        'message',
        'status_after',
        'is_internal',
        'broadcast_sent',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'broadcast_sent' => 'boolean',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(CommunityIncident::class, 'incident_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
