<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\FoodApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Backs src/app/dashboard/deals/page.tsx and food-app-form.tsx. */
class DealsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Deals', [
            'businesses' => Business::with('vouchers')
                ->where('active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Business $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'logoUrl' => $b->logo_url,
                    'aiHint' => $b->ai_hint,
                    'vouchers' => $b->vouchers
                        ->reject(fn ($v) => $v->isExpired())
                        ->map(fn ($v) => [
                            'id' => $v->id,
                            'title' => $v->title,
                            'description' => $v->description,
                            'expiresAt' => $v->expires_at?->toIso8601String(),
                        ])->values(),
                ]),
            'foodApps' => FoodApp::orderBy('name')
                ->get()
                ->map(fn (FoodApp $f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'logoUrl' => $f->logo_url,
                    'websiteUrl' => $f->website_url,
                    'aiHint' => $f->ai_hint,
                    'couponPercentage' => $f->coupon_percentage,
                ]),
            'canManage' => $request->user()->can('manageUsers'),
        ]);
    }

    public function storeBusiness(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'ai_hint' => ['nullable', 'string', 'max:160'],
        ]);

        Business::create([...$validated, 'active' => true]);

        return back()->with('success', 'Business added.');
    }

    public function storeFoodApp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'website_url' => ['required', 'url', 'max:2048'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'ai_hint' => ['nullable', 'string', 'max:160'],
            'coupon_percentage' => ['nullable', 'integer', 'between:0,100'],
        ]);

        FoodApp::create($validated);

        return back()->with('success', 'Food app added.');
    }
}
