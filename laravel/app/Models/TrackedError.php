<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class TrackedError extends Model
{
    use HasFactory;

    protected $fillable = [
        'fingerprint',
        'exception_class',
        'message',
        'file',
        'line',
        'severity',
        'status',
        'occurrences_count',
        'first_seen_at',
        'last_seen_at',
        'context',
        'stack_trace',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'occurrences_count' => 'integer',
            'line' => 'integer',
            'context' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Generate an idempotent error fingerprint from an exception.
     */
    public static function generateFingerprint(Throwable $e): string
    {
        return hash('sha256', get_class($e).':'.$e->getFile().':'.$e->getLine().':'.substr($e->getMessage(), 0, 100));
    }

    /**
     * Record or increment an occurrence of a tracked error.
     *
     * @param  array<string, mixed>  $context
     */
    public static function recordException(Throwable $e, array $context = [], string $severity = 'error'): self
    {
        $fingerprint = self::generateFingerprint($e);

        $record = static::firstOrNew(['fingerprint' => $fingerprint]);

        if ($record->exists) {
            $record->increment('occurrences_count');
            $record->update([
                'last_seen_at' => now(),
                'message' => $e->getMessage(),
                'context' => array_merge($record->context ?? [], $context),
                'status' => $record->status === 'resolved' ? 'unresolved' : $record->status,
            ]);

            return $record->fresh();
        }

        $record->fill([
            'exception_class' => get_class($e),
            'message' => $e->getMessage() ?: 'No message provided',
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'severity' => in_array($severity, ['notice', 'warning', 'error', 'critical']) ? $severity : 'error',
            'status' => 'unresolved',
            'occurrences_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'context' => $context,
            'stack_trace' => substr($e->getTraceAsString(), 0, 8000),
        ]);

        $record->save();

        return $record;
    }

    /**
     * Mark error as resolved.
     */
    public function markResolved(?User $user = null): self
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $user?->id,
        ]);

        return $this;
    }

    /**
     * Mark error as under investigation.
     */
    public function markInvestigating(): self
    {
        $this->update(['status' => 'investigating']);

        return $this;
    }

    /**
     * Scope query to unresolved errors.
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['unresolved', 'investigating']);
    }

    /**
     * Scope query by severity level.
     */
    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope query to recent occurrences.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subDays($days));
    }
}
