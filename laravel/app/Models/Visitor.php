<?php

namespace App\Models;

use App\Enums\VisitorStatus;
use App\Jobs\SendVisitorPassNotification;
use App\Services\Messaging\PhoneNumber;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

class Visitor extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'name', 'contact', 'vehicle', 'id_type', 'id_number', 'type', 'share_token', 'status',
        'expected_at', 'date_range', 'homeowner_id', 'homeowner_name',
        'id_image_url', 'is_blocked', 'checked_in_at', 'checked_out_at', 'expired_at',
        'notify_email', 'notify_sms', 'notify_whatsapp', 'qr_code_path',
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
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'notify_whatsapp' => 'boolean',
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

    /**
     * The channels this visitor's pass can actually go out on: the ones they
     * opted into, that suit the contact given, and that are configured. An
     * email address is not texted, and SMS is not attempted with no Twilio
     * account behind it.
     *
     * @return list<string>
     */
    public function passNotificationChannels(): array
    {
        $isEmail = is_string($this->contact) && filter_var($this->contact, FILTER_VALIDATE_EMAIL) !== false;
        $isPhone = PhoneNumber::toE164($this->contact) !== null;

        return array_values(array_filter([
            $this->notify_email && $isEmail ? 'email' : null,
            $this->notify_sms && $isPhone && app(SmsService::class)->isConfigured() ? 'sms' : null,
            $this->notify_whatsapp && $isPhone && app(WhatsAppService::class)->isConfigured() ? 'whatsapp' : null,
        ]));
    }

    /** The visitor's gate pass: the most recent, if the host re-registered them. */
    public function gatePass(): HasOne
    {
        return $this->hasOne(GatePass::class)->latestOfMany();
    }

    public function sendPassNotification(array $channels = ['email']): void
    {
        SendVisitorPassNotification::dispatch($this, $channels);
    }

    public function resendPassNotification(array $channels = ['email']): void
    {
        // Generate new QR code and resend
        $this->update(['qr_code_path' => null]);
        SendVisitorPassNotification::dispatch($this, $channels);
    }

    /**
     * Get the indexable data array for Scout.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'contact' => (string) $this->contact,
            'vehicle' => (string) $this->vehicle,
            'id_number' => (string) $this->id_number,
            'type' => (string) $this->type,
            'homeowner_name' => (string) $this->homeowner_name,
        ];
    }
}
