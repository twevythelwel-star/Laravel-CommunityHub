<?php

namespace App\Models;

use App\Enums\PassCategory;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Replaces the mockUserDatabase in src/context/auth-context.tsx with real,
 * hashed-credential accounts. `uid` preserves the original string identifier
 * shape so existing front-end code keyed on user.uid keeps working.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'uid', 'name', 'display_name', 'email', 'phone', 'role', 'title',
        'lot', 'street', 'avatar_url', 'status', 'password', 'ai_consent',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'ai_consent' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            $user->uid ??= (string) Str::uuid();
            $user->display_name ??= $user->name;
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function gatePasses(): HasMany
    {
        return $this->hasMany(GatePass::class);
    }

    public function activePass(): HasOne
    {
        return $this->hasOne(GatePass::class)->active()->latestOfMany();
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class, 'homeowner_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'added_by');
    }

    public function renter(): HasOne
    {
        return $this->hasOne(Renter::class);
    }

    public function temporaryOccupancies(): HasMany
    {
        return $this->hasMany(Renter::class, 'homeowner_id');
    }

    public function renters(): HasMany
    {
        return $this->temporaryOccupancies();
    }

    public function activityLog(): HasMany
    {
        return $this->hasMany(ActivityLogEntry::class)->latest('occurred_at');
    }

    public function accessLogEntries(): HasMany
    {
        return $this->hasMany(AccessLogEntry::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function amenityBookings(): HasMany
    {
        return $this->hasMany(AmenityBooking::class);
    }

    public function paymentCustomers(): HasMany
    {
        return $this->hasMany(PaymentCustomer::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_user_id');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function warningResponses(): HasMany
    {
        return $this->hasMany(WarningResponse::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function autoPaySetting(): HasOne
    {
        return $this->hasOne(AutoPaySetting::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function paymentPlans(): HasMany
    {
        return $this->hasMany(PaymentPlan::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class, 'created_by');
    }

    // ── Domain helpers ───────────────────────────────────────────────

    public function isActive(): bool
    {
        if ($this->status !== 'Active' || $this->deactivated_at !== null) {
            return false;
        }

        if ($this->role === UserRole::TemporaryHomeowner) {
            $renter = $this->relationLoaded('renter') ? $this->renter : $this->renter()->first();
            if ($renter && $renter->lease_end && $renter->lease_end->isPast()) {
                return false;
            }
        }

        return true;
    }

    public function temporaryStayExpirationMessage(): ?string
    {
        if ($this->role === UserRole::TemporaryHomeowner) {
            $renter = $this->relationLoaded('renter') ? $this->renter : $this->renter()->first();
            if ($renter && $renter->lease_end && $renter->lease_end->isPast()) {
                return "Your temporary homeowner access expired on {$renter->lease_end->format('M d, Y')}. Contact the property owner to extend access.";
            }
        }

        return null;
    }

    public function activeStay(): ?Renter
    {
        return $this->renter()->active()->first();
    }

    public function passCategory(): PassCategory
    {
        // A staff member attached to a specific residence carries the
        // household-staff category rather than the community-staff one.
        if ($this->role === UserRole::Staff && filled($this->lot)) {
            return PassCategory::HomeownerStaff;
        }

        return $this->role->passCategory();
    }

    /** Property label used on passes and in the directory, e.g. "Lot 42, Royal Palm Drive". */
    public function propertyLabel(): string
    {
        return collect([$this->lot, $this->street])->filter()->implode(', ') ?: 'Unassigned';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active')->whereNull('deactivated_at');
    }

    public function scopeRole($query, UserRole|string $role)
    {
        return $query->where('role', $role instanceof UserRole ? $role->value : $role);
    }

    public function recordActivity(string $action): ActivityLogEntry
    {
        return $this->activityLog()->create([
            'action' => $action,
            'occurred_at' => now(),
        ]);
    }
}
