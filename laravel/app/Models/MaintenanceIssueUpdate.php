<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceIssueUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_issue_id',
        'user_id',
        'from_status',
        'to_status',
        'note',
        'is_internal',
        'photos',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'photos' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MaintenanceIssue::class, 'maintenance_issue_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
