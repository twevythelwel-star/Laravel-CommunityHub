<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\TokenAbility;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CreateTokenApiRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // No later than config('sanctum.expiration') allows: Sanctum expires
        // the token at that point anyway, so a later date would be a promise
        // it does not keep.
        $maxMinutes = (int) config('sanctum.expiration');
        $expiresAt = ['nullable', 'date', 'after:now'];
        if ($maxMinutes > 0) {
            $expiresAt[] = 'before_or_equal:'.now()->addMinutes($maxMinutes)->toIso8601String();
        }

        return [
            'name' => ['required_without:token_name', 'string', 'min:2', 'max:100'],
            'token_name' => ['required_without:name', 'string', 'min:2', 'max:100'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:50'],
            'expires_at' => $expiresAt,
        ];
    }

    public function tokenName(): string
    {
        return $this->validated('token_name') ?? $this->validated('name');
    }

    /**
     * The abilities asked for, or everything the account may grant.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return $this->validated('abilities') ?? TokenAbility::forUser($this->user());
    }

    /**
     * Abilities asked for that the account itself does not hold: a token may
     * be narrower than its account, never wider.
     *
     * @return list<string>
     */
    public function abilitiesBeyondAccount(): array
    {
        return array_values(array_diff($this->abilities(), TokenAbility::forUser($this->user())));
    }

    /**
     * The end asked for, else the configured lifetime, so the token's
     * expiresAt tells the holder the truth instead of null.
     */
    public function expiresAt(): ?CarbonInterface
    {
        if ($requested = $this->validated('expires_at')) {
            return Carbon::parse($requested);
        }

        $maxMinutes = (int) config('sanctum.expiration');

        return $maxMinutes > 0 ? now()->addMinutes($maxMinutes) : null;
    }
}
