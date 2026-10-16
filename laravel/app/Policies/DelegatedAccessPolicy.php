<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DelegatedAccess;
use App\Models\User;
use App\Services\AccessVisibilityService;
use Illuminate\Auth\Access\Response;

/**
 * An authorization is managed (rules, passes, emergency mode, revocation,
 * audit trail) by the homeowner who granted it, or by an administrator.
 */
class DelegatedAccessPolicy
{
    public function manage(User $user, DelegatedAccess $delegation): Response
    {
        return $user->id === $delegation->grantor_user_id || $user->role->isAdministrative()
            ? Response::allow()
            : Response::deny('Unauthorized access to this delegation boundary.');
    }

    /**
     * Determine whether the user can create a delegation.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(User $user, array $input): Response
    {
        if (! ($user->role->isResident() || $user->role->isAdministrative())) {
            return Response::deny('Your role cannot create delegations.');
        }

        $propertyId = $input['property_id'] ?? null;
        if (! empty($propertyId) && ! $user->role->isAdministrative()) {
            $hasProperty = $user->properties()->whereKey($propertyId)->exists()
                || $user->allAuthorizedProperties()->contains('id', (int) $propertyId);

            if (! $hasProperty) {
                return Response::deny('You can only authorize people for your own property.');
            }
        }

        [$isShortTerm, $isRenter] = $this->resolveStayContext($user, $input);

        if ($isShortTerm) {
            $accessLevel = $input['access_level'] ?? '';
            if (! in_array($accessLevel, ['Visitor', 'Guest Pass', 'Day Visitor', 'Pre-Cleared Visitor'], true)) {
                return Response::deny('Short-term guests can only register visitors for their reservation stay.');
            }
        } elseif ($isRenter) {
            if (($input['authorization_type'] ?? '') === 'short_term_rental' || stripos($input['access_level'] ?? '', 'short') !== false) {
                return Response::deny('Renters are not permitted to authorize short-term rentals.');
            }
            if (($input['access_level'] ?? '') === 'Homeowner' || ($input['relationship'] ?? '') === 'Homeowner') {
                return Response::deny('Renters cannot configure homeowner credentials or change property ownership.');
            }
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can revoke the delegation.
     *
     * @param  array<string, mixed>  $context
     */
    public function revoke(User $user, DelegatedAccess $delegation, array $context = []): Response
    {
        if ($user->role->isAdministrative()) {
            return Response::allow();
        }

        [$isShortTerm, $isRenter] = $this->resolveStayContext($user, $context);

        if ($isShortTerm || $isRenter) {
            if ($delegation->grantor_user_id !== $user->id) {
                return Response::deny($isShortTerm
                    ? 'Short-term guests cannot revoke property-level authorizations granted by the homeowner.'
                    : 'Renters cannot revoke property-level authorizations granted by the homeowner.');
            }

            $delegateUser = $delegation->delegate ?? User::where('email', $delegation->email)->first();
            if (($delegateUser && $delegateUser->role === UserRole::Homeowner) || $delegation->relationship === 'Homeowner' || $delegation->access_level === 'Homeowner') {
                return Response::deny($isShortTerm
                    ? 'Short-term guests cannot remove or revoke the property homeowner.'
                    : 'Renters cannot remove or revoke the property homeowner.');
            }
        }

        return $this->manage($user, $delegation);
    }

    /**
     * Determine whether the user can update delegation rules.
     *
     * @param  array<string, mixed>  $context
     */
    public function updateRules(User $user, DelegatedAccess $delegation, array $context = []): Response
    {
        if ($user->role->isAdministrative()) {
            return Response::allow();
        }

        [$isShortTerm] = $this->resolveStayContext($user, $context);

        if ($isShortTerm) {
            if ($delegation->grantor_user_id !== $user->id) {
                return Response::deny('Short-term guests cannot modify rules for property services or host credentials.');
            }
        }

        return $this->manage($user, $delegation);
    }

    /**
     * Determine whether the user can save an emergency continuity plan.
     *
     * @param  array<string, mixed>  $context
     */
    public function saveContinuityPlan(User $user, array $context = []): Response
    {
        [$isShortTerm, $isRenter] = $this->resolveStayContext($user, $context);

        if ($isShortTerm || $isRenter) {
            return Response::deny('Emergency & Incapacity Continuity Plans are reserved for property homeowners.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can accept the delegation invitation.
     */
    public function accept(User $user, DelegatedAccess $delegation): Response
    {
        return strcasecmp($delegation->email, $user->email) === 0
            ? Response::allow()
            : Response::deny('This invitation is not for your account, or has already been used.');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: bool, 1: bool}
     */
    private function resolveStayContext(User $user, array $input): array
    {
        $visibilityService = app(AccessVisibilityService::class);
        $roleName = $visibilityService->resolveUserRole($user);
        $viewAs = strtolower((string) ($input['view_as'] ?? ''));
        $renterRecord = $user->renter ?? $user->activeStay();

        $isShortTermRenter = false;
        if ($renterRecord) {
            $stay = strtolower((string) ($renterRecord->stay_type ?? ''));
            $notes = strtolower((string) ($renterRecord->notes ?? ''));
            if (str_contains($stay, 'short') || str_contains($stay, 'airbnb') || str_contains($notes, 'airbnb') || str_contains($stay, 'vacation')) {
                $isShortTermRenter = true;
            }
        }

        $isShortTerm = in_array($viewAs, ['short_term', 'airbnb', 'guest', 'vacation'], true)
            || $roleName === 'Short-Term Rental / Airbnb Host/Guest'
            || $roleName === 'Short-Term Renter'
            || $isShortTermRenter;

        $isRenter = ! $isShortTerm && ($roleName === 'Long-Term Renter' || $viewAs === 'renter');

        return [$isShortTerm, $isRenter];
    }
}
