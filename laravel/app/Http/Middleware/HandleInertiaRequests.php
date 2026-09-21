<?php

namespace App\Http\Middleware;

use App\Models\BillingSetting;
use App\Models\BrandingSetting;
use App\Models\Community;
use App\Services\GeofenceService;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shares the data the React layout previously pulled from AuthContext,
 * BrandingContext, ThemeContext and BillingContext. Because it is shared on
 * every Inertia response, those providers can read from page props instead of
 * localStorage, and every device sees the same branding.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [

            'auth' => [
                'user' => $user ? [
                    'uid' => $user->uid,
                    'name' => $user->name,
                    'displayName' => $user->display_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role->value,
                    'lot' => $user->lot,
                    'street' => $user->street,
                    'title' => $user->title,
                    'avatarUrl' => $user->avatar_url,
                    'aiConsent' => $user->ai_consent,
                ] : null,
                'justSignedIn' => (bool) $request->session()->get('just_signed_in', false),

                // Drives menu visibility; previously hardcoded role checks in JSX.
                'can' => $user ? [
                    'manageUsers' => $user->can('manageUsers'),
                    'manageSecurity' => $user->can('manageSecurity'),
                    'scanPasses' => $user->can('scanPasses'),
                    'manageBoundary' => $user->can('manageBoundary'),
                    'manageBilling' => $user->can('manageBilling'),
                    'reviewFeedback' => $user->can('reviewFeedback'),
                    'manageBlocklist' => $user->can('manageBlocklist'),
                    'broadcastNotices' => $user->can('broadcastNotices'),
                    'manageFundraisers' => $user->can('manageFundraisers'),
                    'registerVisitors' => $user->can('registerVisitors'),
                    'registerStaff' => $user->can('registerStaff'),
                ] : [],
            ],

            'branding' => fn () => $this->branding(),

            /*
             | Published community boundary.
             |
             | Replaces MapProvider, which kept the polygon in localStorage — so
             | every browser had its own idea of where the estate ended, and an
             | admin publishing a new boundary changed it for nobody else.
             |
             | Shared globally (it is eight coordinate pairs) so useMap() works on
             | any page, and wrapped in a closure so partial reloads skip it.
             */
            'boundary' => fn () => $this->boundary(),

            'theme' => [
                'preference' => $user?->preferences?->theme ?? 'system',
            ],

            /*
             | Community fee settings. Replaces BillingProvider, whose monthlyFee
             | lived in React state and reset to JMD 5,000 on every page load, so
             | an administrator changing it changed nothing for anyone.
             */
            'billing' => fn () => $this->billing(),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],

            'ziggy' => fn () => [
                'location' => $request->url(),
            ],
        ]);
    }

    private function billing(): array
    {
        $settings = BillingSetting::query()->first();

        return [
            'monthlyFee' => (float) (($settings?->monthly_fee_minor ?? 500000) / 100),
            'currency' => $settings?->currency ?? 'JMD',
            'dueDayOfMonth' => $settings?->due_day_of_month ?? 1,
        ];
    }

    /**
     * The live boundary, plus its derived metrics so the client does not have to
     * recompute area and perimeter on every render.
     */
    private function boundary(): array
    {
        $community = Community::query()
            ->where('code', config('gatepass.default_community_id'))
            ->first();

        $published = $community?->publishedBoundary();
        $polygon = $published?->published_coordinates ?? [];

        return [
            'coordinates' => $polygon,
            'version' => $published?->version,
            'publishedAt' => $published?->last_published_at?->toIso8601String(),
            'metrics' => app(GeofenceService::class)->polygonMetrics($polygon),
            'community' => [
                'name' => $community?->name,
                'code' => $community?->code,
                'datum' => $community?->datum,
            ],
        ];
    }

    private function branding(): array
    {
        $branding = BrandingSetting::query()->first();

        return [
            'appName' => $branding?->app_name ?? config('app.name'),
            'logoUrl' => $branding?->logo_url,
            'primaryColor' => $branding?->primary_color,
            'accentColor' => $branding?->accent_color,
            'backgroundColor' => $branding?->background_color,
            'defaultTheme' => $branding?->default_theme ?? 'system',
            'themeTokens' => $branding?->theme_tokens ?? [],
        ];
    }
}
