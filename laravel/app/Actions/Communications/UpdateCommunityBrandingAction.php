<?php

namespace App\Actions\Communications;

use App\Models\BrandingSetting;
use App\Models\Community;
use App\Models\User;

/**
 * Estate-wide branding and the community's name. The name is kept in two
 * places, the community record and the communityName theme token, and
 * both change together. A blank name changes nothing.
 */
class UpdateCommunityBrandingAction
{
    /**
     * @param  array<string, mixed>  $branding
     */
    public function execute(User $actor, array $branding, ?string $communityName): void
    {
        if ($branding !== []) {
            BrandingSetting::current()->update($branding);
        }

        $name = trim((string) $communityName);
        if ($name !== '') {
            Community::query()->update(['name' => $name]);

            $current = BrandingSetting::current();
            $current->update(['theme_tokens' => [...($current->theme_tokens ?? []), 'communityName' => $name]]);
        }

        $actor->recordActivity('Updated community branding and name');
    }
}
