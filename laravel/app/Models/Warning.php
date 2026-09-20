<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Community warnings. Vote tallies are derived from warning_responses rather than stored counters. */
class Warning extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'author_id', 'author_name', 'issued_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(WarningResponse::class);
    }

    public function confirmsCount(): int
    {
        return $this->responses()->where('response', 'confirmed')->count();
    }

    public function deniesCount(): int
    {
        return $this->responses()->where('response', 'denied')->count();
    }

    /** The current viewer vote: confirmed, denied, or null. */
    public function responseFor(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return $this->responses->firstWhere('user_id', $user->id)?->response;
    }
}
