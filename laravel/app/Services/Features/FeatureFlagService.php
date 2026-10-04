<?php

declare(strict_types=1);

namespace App\Services\Features;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Pennant\Feature;

class FeatureFlagService
{
    /**
     * Register all enterprise feature flags into Laravel Pennant.
     */
    public function registerFeatures(): void
    {
        // 1. FEATURE.ENABLED: Global Platform Toggles
        Feature::define('rfid_gate_scanner', function () {
            return (bool) config('features.rfid_gate_scanner', true);
        });

        Feature::define('instant_visitor_qr', function () {
            return (bool) config('features.instant_visitor_qr', true);
        });

        // 2. BETA.FEATURES: User Opt-In / Early Access Features
        Feature::define('ai_visitor_analytics', function (?User $user = null) {
            if (! $user) {
                return false;
            }

            return (bool) $user->ai_consent
                || str_ends_with($user->email, '@communityhub.test')
                || str_contains($user->email, 'beta');
        });

        Feature::define('dark_mode_v2', function (?User $user = null) {
            if (! $user) {
                return false;
            }

            return str_ends_with($user->email, '@communityhub.test')
                || (bool) (((int) ($user->getKey() ?? $user->id)) % 2 === 0);
        });

        // 3. A/B TESTING: Multi-Variant Experimentation
        Feature::define('checkout_flow_experiment', function (?User $user = null) {
            if (! $user) {
                return 'classic';
            }

            $userId = (int) ($user->getKey() ?? $user->id);
            $bucket = abs(crc32((string) $userId.'_checkout_v1')) % 3;

            return match ($bucket) {
                0 => 'classic',
                1 => 'streamlined',
                default => 'express_one_click',
            };
        });

        // 4. GRADUAL ROLLOUT: Percentage-Based Progressive Delivery
        Feature::define('new_resident_portal', function (?User $user = null) {
            if (! $user) {
                return false;
            }

            $userId = (int) ($user->getKey() ?? $user->id);

            // 40% progressive rollout based on user ID modulo
            return ($userId % 100) < 40;
        });

        Feature::define('biometric_visitor_pass', function (?User $user = null) {
            if (! $user) {
                return false;
            }

            $userId = (int) ($user->getKey() ?? $user->id);

            // 25% progressive rollout
            return ($userId % 100) < 25;
        });

        // 5. TENANT-SPECIFIC FEATURES: Scoped to Gated Communities & Estates
        Feature::define('automated_barrier_motor', function (mixed $scope = null) {
            if ($scope instanceof Tenant) {
                return (bool) $scope->getSetting('automated_barrier', in_array($scope->id, ['palm-grove', 'ocean-ridge', 'central-estate']));
            }

            if (is_string($scope)) {
                return in_array($scope, ['palm-grove', 'ocean-ridge', 'central-estate']);
            }

            return false;
        });

        Feature::define('valet_parking_module', function (mixed $scope = null) {
            if ($scope instanceof Tenant) {
                return (bool) $scope->getSetting('valet_parking', in_array($scope->id, ['ocean-ridge', 'luxury-villas']));
            }

            if (is_string($scope)) {
                return in_array($scope, ['ocean-ridge', 'luxury-villas']);
            }

            return false;
        });

        // 6. ROLE-SPECIFIC FEATURES: Scoped to User Roles (Admin, Security, Resident)
        Feature::define('advanced_audit_tools', function (?User $user = null) {
            if (! $user) {
                return false;
            }

            $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

            return $role?->isAdministrative() ?? false;
        });

        Feature::define('security_dispatch_hub', function (?User $user = null) {
            if (! $user) {
                return false;
            }

            $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

            return in_array($role, [UserRole::SystemAdmin, UserRole::Admin, UserRole::Security], true);
        });
    }

    /**
     * Get the full feature flag catalog with metadata and types.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCatalog(): array
    {
        return [
            // 1. Boolean Global
            'rfid_gate_scanner' => [
                'name' => 'RFID Gate Scanner Integration',
                'type' => 'boolean_global',
                'description' => 'Activates low-level physical RFID tag reader hardware communication at gate checkpoints.',
                'scope_target' => 'global',
                'default' => true,
                'use_case' => 'feature.enabled',
            ],
            'instant_visitor_qr' => [
                'name' => 'Instant Visitor QR Pass Generation',
                'type' => 'boolean_global',
                'description' => 'Enables residents to generate self-service single-use QR codes instantly.',
                'scope_target' => 'global',
                'default' => true,
                'use_case' => 'feature.enabled',
            ],

            // 2. Beta Features
            'ai_visitor_analytics' => [
                'name' => 'AI Visitor Analytics & Sentiment',
                'type' => 'beta_opt_in',
                'description' => 'Machine learning models analyzing visitor arrival frequency, peak hours, and vehicle dwell times.',
                'scope_target' => 'user',
                'default' => false,
                'use_case' => 'beta.features',
            ],
            'dark_mode_v2' => [
                'name' => 'Next-Gen Dark Mode Theme V2',
                'type' => 'beta_opt_in',
                'description' => 'High-contrast OLED black aesthetic with customized accent palettes for beta participants.',
                'scope_target' => 'user',
                'default' => false,
                'use_case' => 'beta.features',
            ],

            // 3. A/B Testing
            'checkout_flow_experiment' => [
                'name' => 'HOA Dues Checkout Experience',
                'type' => 'ab_testing',
                'description' => 'Multi-variant experiment evaluating checkout conversion rates across classic, streamlined, and one-click flows.',
                'scope_target' => 'user',
                'variants' => ['classic', 'streamlined', 'express_one_click'],
                'default' => 'classic',
                'use_case' => 'A/B testing',
            ],

            // 4. Gradual Rollout
            'new_resident_portal' => [
                'name' => 'New Resident Portal Experience',
                'type' => 'gradual_rollout',
                'description' => 'Percentage-based canary rollout for redesigned property directory and guest passes.',
                'scope_target' => 'user',
                'rollout_percentage' => 40,
                'default' => false,
                'use_case' => 'gradual rollout',
            ],
            'biometric_visitor_pass' => [
                'name' => 'Biometric Facial Recognition Pass',
                'type' => 'gradual_rollout',
                'description' => 'Progressive 25% rollout of camera-based facial pass validation for registered guests.',
                'scope_target' => 'user',
                'rollout_percentage' => 25,
                'default' => false,
                'use_case' => 'gradual rollout',
            ],

            // 5. Tenant-Specific
            'automated_barrier_motor' => [
                'name' => 'Automated Gate Barrier Hardware Integration',
                'type' => 'tenant_specific',
                'description' => 'Direct motor relay control for automated vehicle boom barriers and spike strips.',
                'scope_target' => 'tenant',
                'default' => false,
                'use_case' => 'tenant-specific features',
            ],
            'valet_parking_module' => [
                'name' => 'Valet Parking & Guest Car Queuing',
                'type' => 'tenant_specific',
                'description' => 'Digital valet ticket dispatch, vehicle retrieval notifications, and parking slot tracking.',
                'scope_target' => 'tenant',
                'default' => false,
                'use_case' => 'tenant-specific features',
            ],

            // 6. Role-Specific
            'advanced_audit_tools' => [
                'name' => 'Advanced Audit & Security Intelligence',
                'type' => 'role_specific',
                'description' => 'Executive audit inspection, IP geofencing analytics, and compliance export tools.',
                'scope_target' => 'role',
                'eligible_roles' => ['System Admin', 'Admin'],
                'default' => false,
                'use_case' => 'role-specific features',
            ],
            'security_dispatch_hub' => [
                'name' => 'Real-Time Guard Patrol & Dispatch Hub',
                'type' => 'role_specific',
                'description' => 'Live tactical map with GPS guard tracking, panic alarm alerts, and radio dispatches.',
                'scope_target' => 'role',
                'eligible_roles' => ['System Admin', 'Admin', 'Security'],
                'default' => false,
                'use_case' => 'role-specific features',
            ],
        ];
    }

    /**
     * Check if a feature is active for the given or current scope.
     */
    public function active(string $feature, mixed $scope = null): bool
    {
        return $scope !== null
            ? Feature::for($scope)->active($feature)
            : Feature::active($feature);
    }

    /**
     * Retrieve the resolved value for a feature (e.g. variant string or boolean).
     */
    public function value(string $feature, mixed $scope = null): mixed
    {
        return $scope !== null
            ? Feature::for($scope)->value($feature)
            : Feature::value($feature);
    }

    /**
     * Activate a feature flag for all or a specific scope.
     */
    public function activate(string $feature, mixed $value = true, mixed $scope = null): void
    {
        if ($scope !== null) {
            Feature::for($scope)->activate($feature, $value);
        } else {
            Feature::activate($feature, $value);
        }
    }

    /**
     * Deactivate a feature flag.
     */
    public function deactivate(string $feature, mixed $scope = null): void
    {
        if ($scope !== null) {
            Feature::for($scope)->deactivate($feature);
        } else {
            Feature::deactivate($feature);
        }
    }

    /**
     * Purge resolved feature values from cache/database store.
     */
    public function purge(?string $feature = null): void
    {
        if ($feature !== null) {
            Feature::purge($feature);
        } else {
            Feature::purge();
        }
    }

    /**
     * Resolve all features for a given user or scope.
     *
     * @return array<string, mixed>
     */
    public function allFor(mixed $scope = null): array
    {
        $catalog = $this->getCatalog();
        $resolved = [];

        foreach (array_keys($catalog) as $featureName) {
            $resolved[$featureName] = [
                'active' => $this->active($featureName, $scope),
                'value' => $this->value($featureName, $scope),
                'type' => $catalog[$featureName]['type'],
                'use_case' => $catalog[$featureName]['use_case'],
            ];
        }

        return $resolved;
    }

    /**
     * Simulate resolution for a hypothetical or actual User, Role, or Tenant.
     *
     * @return array<string, mixed>
     */
    public function simulate(string $feature, string $scopeType, mixed $scopeIdentifier): array
    {
        $catalog = $this->getCatalog();
        if (! isset($catalog[$feature])) {
            return [
                'feature' => $feature,
                'valid' => false,
                'error' => "Feature [{$feature}] is not registered in the catalog.",
            ];
        }

        $meta = $catalog[$feature];
        $scopeObject = null;

        if ($scopeType === 'user') {
            if ($scopeIdentifier instanceof User) {
                $scopeObject = $scopeIdentifier;
            } elseif (is_numeric($scopeIdentifier)) {
                $scopeObject = User::find($scopeIdentifier);
                if (! $scopeObject) {
                    $scopeObject = new User(['email' => 'simulated@user.com']);
                    $scopeObject->id = (int) $scopeIdentifier;
                }
            } else {
                $scopeObject = new User(['email' => (string) $scopeIdentifier]);
                $scopeObject->id = 999;
            }
        } elseif ($scopeType === 'role') {
            $scopeObject = new User([
                'name' => 'Simulated Role User',
                'role' => $scopeIdentifier,
            ]);
            $scopeObject->id = 888;
        } elseif ($scopeType === 'tenant') {
            if ($scopeIdentifier instanceof Tenant) {
                $scopeObject = $scopeIdentifier;
            } else {
                $scopeObject = Tenant::find($scopeIdentifier) ?? new Tenant(['id' => (string) $scopeIdentifier]);
            }
        }

        $val = $this->value($feature, $scopeObject);
        $isActive = $this->active($feature, $scopeObject);

        return [
            'feature' => $feature,
            'name' => $meta['name'],
            'type' => $meta['type'],
            'use_case' => $meta['use_case'],
            'scope_type' => $scopeType,
            'scope_identifier' => is_object($scopeIdentifier) ? ($scopeIdentifier->id ?? 'object') : (string) $scopeIdentifier,
            'resolved_value' => $val,
            'is_active' => $isActive,
        ];
    }
}
