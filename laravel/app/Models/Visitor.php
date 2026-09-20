<?php

namespace App\Models;

use App\Enums\VisitorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'contact', 'vehicle', 'id_type', 'id_number', 'type', 'share_token', 'status',
        'expected_at', 'date_range', 'homeowner_id', 'homeowner_name',
        'id_image_url', 'is_blocked', 'checked_in_at', 'checked_out_at', 'expired_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Visitor $visitor) {
            if (empty($visitor->share_token)) {
                $visitor->share_token = bin2hex(random_bytes(16));
            }
        });
    }

    public function guestPassUrl(): string
    {
        return route('guest-pass.show', ['token' => $this->share_token]);
    }

    protected function casts(): array
    {
        return [
            'expected_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'expired_at' => 'datetime',
            'is_blocked' => 'boolean',
            'status' => VisitorStatus::class,
        ];
    }

    public function homeowner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeowner_id');
    }

    public function scopeExpected($query)
    {
        return $query->where('status', VisitorStatus::Expected->value)->whereNull('expired_at');
    }

    /**
     * Expected visitors who never arrived within the grace period.
     *
     * The original swept these in a browser setInterval, so it only ran while
     * somebody had the visitors page open and removed nothing from storage.
     */
    public function scopeNoShow($query, int $graceHours = 12)
    {
        return $query->where('status', VisitorStatus::Expected->value)
            ->whereNull('expired_at')
            ->where('expected_at', '<', now()->subHours($graceHours));
    }

    public function scopeOnSite($query)
    {
        return $query->where('status', VisitorStatus::CheckedIn->value);
    }

    public function checkIn(): void
    {
        $this->update(['status' => VisitorStatus::CheckedIn, 'checked_in_at' => now()]);
    }

    public function checkOut(): void
    {
        $this->update(['status' => VisitorStatus::CheckedOut, 'checked_out_at' => now()]);
    }
}
