<?php

namespace App\Services\Communications;

use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Enums\VisitorStatus;
use App\Models\DelegatedAccess;
use App\Models\GatePass;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who an emergency broadcast reaches.
 *
 * Audiences: all, residents, staff, security, building (a street or lot
 * fragment), property (one lot), legacy_contacts (active delegates),
 * long_term_guests and visitors (checked in now). Accounts get in-app, push
 * and email; phone numbers come from accounts and from the people with no
 * account: delegates, long-term guests and visitors on site.
 */
class EmergencyAudience
{
    /** Audiences whose phone numbers include the matched accounts'. */
    private const ACCOUNT_AUDIENCES = ['all', 'residents', 'staff', 'security', 'building', 'property'];

    /**
     * The roles a stored notice is shown to; null is everyone.
     *
     * @return list<string>|null
     */
    public function noticeRoles(string $audience): ?array
    {
        return match ($audience) {
            'residents' => [UserRole::Homeowner->value, UserRole::TemporaryHomeowner->value],
            'staff' => [UserRole::Staff->value],
            'security' => [UserRole::Security->value],
            default => null,
        };
    }

    /**
     * Active accounts in the audience. Visitors have none.
     *
     * @return Collection<int, User>
     */
    public function users(string $audience, ?string $building = null, ?string $property = null): Collection
    {
        if ($audience === 'visitors') {
            return collect();
        }

        $query = User::query()->whereNull('deactivated_at');

        match ($audience) {
            'residents' => $query->whereIn('role', [UserRole::Homeowner->value, UserRole::TemporaryHomeowner->value]),
            'staff' => $query->where('role', UserRole::Staff->value),
            'security' => $query->where('role', UserRole::Security->value),
            'building' => $query->when($building, fn (Builder $q) => $this->onBuilding($q, $building)),
            'property' => $query->when($property, fn (Builder $q) => $this->atProperty($q, $property)),
            default => null,
        };

        return $query->get();
    }

    /**
     * Distinct phone numbers to text, from the given accounts and from
     * whoever in the audience has no account.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, string>
     */
    public function phones(string $audience, Collection $users, ?string $property = null): Collection
    {
        $phones = collect();

        if (in_array($audience, self::ACCOUNT_AUDIENCES, true)) {
            $phones = $phones->merge($users->pluck('phone'));
        }

        if (in_array($audience, ['all', 'legacy_contacts'], true)) {
            $phones = $phones->merge(DelegatedAccess::where('status', 'active')->whereNotNull('phone')->pluck('phone'));
        }

        if (in_array($audience, ['all', 'long_term_guests'], true)) {
            $phones = $phones->merge(
                GatePass::where('category', PassCategory::LongTermOccupant)
                    ->whereIn('status', [PassStatus::Active, PassStatus::CheckedIn])
                    ->get()
                    ->map(fn (GatePass $p) => $p->metadata['contact'] ?? null),
            );
        }

        if (in_array($audience, ['all', 'visitors'], true) || ($audience === 'property' && $property)) {
            $phones = $phones->merge($this->visitorPhones($audience === 'property' ? $property : null));
        }

        return $phones->filter()->unique()->values();
    }

    /** Contacts of visitors checked in now, optionally only those visiting one lot. */
    private function visitorPhones(?string $property): Collection
    {
        return Visitor::query()
            ->where('status', VisitorStatus::CheckedIn->value)
            ->when($property, fn (Builder $q) => $q->whereHas(
                'homeowner',
                fn (Builder $sub) => $sub->where('lot', $this->lotNumber($property) ?: $property),
            ))
            ->whereNotNull('contact')
            ->pluck('contact');
    }

    private function onBuilding(Builder $query, string $building): void
    {
        $term = strtolower(trim($building));

        $query->where(fn (Builder $q) => $q
            ->whereRaw('LOWER(street) LIKE ?', ["%{$term}%"])
            ->orWhereRaw('LOWER(lot) LIKE ?', ["%{$term}%"]));
    }

    private function atProperty(Builder $query, string $property): void
    {
        $unit = $this->lotNumber($property) ?: trim($property);

        $query->where(fn (Builder $q) => $q
            ->where('lot', $unit)
            ->orWhere('lot', $property)
            ->orWhereRaw('LOWER(street) LIKE ?', ['%'.strtolower($property).'%']));
    }

    /** "Lot 14B" → "14": the digits a lot number is stored as. */
    private function lotNumber(string $property): string
    {
        return preg_replace('/[^0-9]/', '', $property);
    }
}
