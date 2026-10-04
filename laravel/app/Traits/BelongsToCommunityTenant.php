<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Trait to provide seamless single-database multi-tenancy scoping.
 * Scopes records to the active tenant when tenancy is initialized.
 */
trait BelongsToCommunityTenant
{
    use CentralConnection;

    public static function bootBelongsToCommunityTenant(): void
    {
        // Global scope to isolate records to the current initialized tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (function_exists('tenancy') && tenancy()->initialized) {
                $tenantKey = tenant()->getTenantKey();
                $column = static::getTenantColumnName();
                $builder->where($builder->getModel()->qualifyColumn($column), $tenantKey);
            }
        });

        // Automatically associate new records with active tenant
        static::creating(function (Model $model) {
            $column = static::getTenantColumnName();

            if (empty($model->getAttribute($column))) {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    $model->setAttribute($column, tenant()->getTenantKey());
                }
            }
        });
    }

    /**
     * Column name used to associate with the tenant.
     * Defaults to 'tenant_id'. Can be overridden in models.
     */
    public static function getTenantColumnName(): string
    {
        return property_exists(static::class, 'tenantColumn')
            ? static::$tenantColumn
            : 'tenant_id';
    }

    /**
     * Relationship to the Tenant model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, static::getTenantColumnName());
    }

    /**
     * Scope to bypass tenant isolation (e.g. for superadmin central analytics).
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Scope to explicitly filter by a specific tenant.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')
            ->where(static::getTenantColumnName(), $tenantId);
    }
}
