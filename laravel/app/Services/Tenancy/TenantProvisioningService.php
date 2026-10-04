<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * Create and provision a new multi-tenant instance.
     *
     * @param array{
     *     id?: string,
     *     name: string,
     *     domain?: string,
     *     community_id?: int|string,
     *     estate_name?: string,
     *     app_name?: string,
     *     settings?: array<string, mixed>,
     * } $data
     */
    public function provision(array $data): Tenant
    {
        $id = $data['id'] ?? Str::slug($data['name']);

        $tenant = Tenant::create([
            'id' => $id,
            'name' => $data['name'],
            'community_id' => $data['community_id'] ?? null,
            'estate_name' => $data['estate_name'] ?? $data['name'],
            'app_name' => $data['app_name'] ?? ($data['name'].' Hub'),
            'settings' => $data['settings'] ?? [],
        ]);

        if (! empty($data['domain'])) {
            $tenant->domains()->create([
                'domain' => $data['domain'],
            ]);
        }

        return $tenant;
    }

    /**
     * Get or create a tenant for a community.
     */
    public function getOrCreateForCommunity(int|string $communityId, string $name, ?string $domain = null): Tenant
    {
        $slug = Str::slug($name);
        $tenant = Tenant::find($slug) ?? Tenant::where('community_id', $communityId)->first();

        if ($tenant) {
            return $tenant;
        }

        return $this->provision([
            'id' => $slug,
            'name' => $name,
            'community_id' => $communityId,
            'estate_name' => $name,
            'app_name' => $name.' Community Hub',
            'domain' => $domain,
        ]);
    }
}
