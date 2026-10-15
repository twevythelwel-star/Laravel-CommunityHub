<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'title',
        'token',
        'expected_at',
        'max_guests',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expected_at' => 'datetime',
            'is_active' => 'boolean',
            'max_guests' => 'integer',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function rsvpUrl(): string
    {
        return route('rsvp.show', $this->token);
    }

    /** Still taking RSVPs: not switched off and the event has not started. */
    public function isOpen(): bool
    {
        return $this->is_active && ! $this->expected_at->isPast();
    }
}
