<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Features\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagApiController extends Controller
{
    /**
     * Any token holder may read their own flags; changing a flag changes the
     * platform for everybody, so that is the System Admin's. Also on the
     * routes; declared here so a regrouped routes/api.php cannot open it.
     */
    public function __construct(
        protected FeatureFlagService $featureFlagService
    ) {
        $this->middleware(['auth:sanctum', 'active']);
        $this->middleware('can:operatePlatform')->only(['activate', 'deactivate', 'purge', 'simulate']);
    }

    /**
     * List all resolved feature flags for the current user and context.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $flags = $this->featureFlagService->allFor($user);

        return ApiResponse::success(
            [
                'user_id' => $user?->id,
                'role' => $user?->role?->value ?? (string) $user?->role,
                'count' => count($flags),
                'features' => $flags,
            ],
            'Active feature flags resolved for current context.'
        );
    }

    /**
     * Catalog of all registered feature flags, metadata, and experimentation types.
     */
    public function catalog(): JsonResponse
    {
        $catalog = $this->featureFlagService->getCatalog();

        return ApiResponse::success(
            [
                'total_features' => count($catalog),
                'catalog' => $catalog,
            ],
            'Enterprise feature flag catalog retrieved.'
        );
    }

    /**
     * Check resolution of a single feature flag.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feature' => 'required|string',
            'user_id' => 'nullable|integer',
            'tenant_id' => 'nullable|string',
        ]);

        // Another user's or a tenant's flags are not the caller's to inspect.
        $othersScope = ! empty($validated['tenant_id'])
            || (! empty($validated['user_id']) && (int) $validated['user_id'] !== $request->user()->id);

        if ($othersScope) {
            $this->authorize('operatePlatform');
        }

        $scope = null;
        if (! empty($validated['user_id'])) {
            $scope = User::find($validated['user_id']);
        } elseif (! empty($validated['tenant_id'])) {
            $scope = Tenant::find($validated['tenant_id']) ?? new Tenant(['id' => $validated['tenant_id']]);
        } else {
            $scope = $request->user();
        }

        $feature = $validated['feature'];
        $active = $this->featureFlagService->active($feature, $scope);
        $value = $this->featureFlagService->value($feature, $scope);

        return ApiResponse::success(
            [
                'feature' => $feature,
                'active' => $active,
                'value' => $value,
                'scope_type' => $scope ? class_basename($scope) : 'global',
                'scope_id' => $scope?->id ?? null,
            ],
            "Feature flag [{$feature}] evaluated."
        );
    }

    /**
     * Activate a feature flag globally or for a specific scope.
     */
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feature' => 'required|string',
            'value' => 'nullable',
            'user_id' => 'nullable|integer',
            'tenant_id' => 'nullable|string',
        ]);

        $scope = null;
        if (! empty($validated['user_id'])) {
            $scope = User::findOrFail($validated['user_id']);
        } elseif (! empty($validated['tenant_id'])) {
            $scope = Tenant::findOrFail($validated['tenant_id']);
        }

        $value = $validated['value'] ?? true;
        $this->featureFlagService->activate($validated['feature'], $value, $scope);

        return ApiResponse::success(
            [
                'feature' => $validated['feature'],
                'activated_value' => $value,
                'scope' => $scope ? class_basename($scope).' #'.$scope->id : 'global',
            ],
            "Feature [{$validated['feature']}] activated."
        );
    }

    /**
     * Deactivate a feature flag globally or for a specific scope.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feature' => 'required|string',
            'user_id' => 'nullable|integer',
            'tenant_id' => 'nullable|string',
        ]);

        $scope = null;
        if (! empty($validated['user_id'])) {
            $scope = User::findOrFail($validated['user_id']);
        } elseif (! empty($validated['tenant_id'])) {
            $scope = Tenant::findOrFail($validated['tenant_id']);
        }

        $this->featureFlagService->deactivate($validated['feature'], $scope);

        return ApiResponse::success(
            [
                'feature' => $validated['feature'],
                'scope' => $scope ? class_basename($scope).' #'.$scope->id : 'global',
            ],
            "Feature [{$validated['feature']}] deactivated."
        );
    }

    /**
     * Purge feature flag resolution cache.
     */
    public function purge(Request $request): JsonResponse
    {
        $feature = $request->input('feature');
        $this->featureFlagService->purge($feature);

        return ApiResponse::success(
            [
                'purged_feature' => $feature ?? 'ALL_FEATURES',
            ],
            'Feature flag resolution store purged successfully.'
        );
    }

    /**
     * Simulate feature flag resolution for any user, role, or tenant.
     */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feature' => 'required|string',
            'scope_type' => 'required|string|in:user,role,tenant',
            'scope_value' => 'required',
        ]);

        $result = $this->featureFlagService->simulate(
            $validated['feature'],
            $validated['scope_type'],
            $validated['scope_value']
        );

        return ApiResponse::success(
            $result,
            'Feature resolution simulation computed.'
        );
    }
}
