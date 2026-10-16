<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'community_id',
        'reporter_id',
        'property_id',
        'category',
        'title',
        'description',
        'location',
        'latitude',
        'longitude',
        'priority',
        'status',
        'assigned_to_user_id',
        'assigned_at',
        'photos',
        'resolution_notes',
        'resolved_at',
        'closed_at',
        'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(MaintenanceIssueUpdate::class)->latest();
    }

    public static function generateReferenceNumber(): string
    {
        $year = date('Y');
        $count = self::whereYear('created_at', $year)->count() + 1;

        return sprintf('MNT-%s-%04d', $year, $count);
    }
}
