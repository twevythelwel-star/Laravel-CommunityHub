<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\StoreBusinessDealRequest;
use App\Http\Requests\Deals\StoreFoodAppDealRequest;
use App\Http\Requests\Deals\StoreVoucherDealRequest;
use App\Models\Business;
use App\Models\FoodApp;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Backs src/app/dashboard/deals/page.tsx, voucher-form.tsx, and food-app-form.tsx. */
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

    public function storeBusiness(StoreBusinessDealRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Business::create([...$validated, 'active' => true]);

        return back()->with('success', 'Business added.');
    }

    public function destroyBusiness(Request $request, Business $business): RedirectResponse
    {
        $this->authorize('manageUsers');

        $business->update(['active' => false]);

        return back()->with('success', 'Business deactivated.');
    }

    public function storeFoodApp(StoreFoodAppDealRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        FoodApp::create($validated);

        return back()->with('success', 'Food delivery option added.');
    }

    public function destroyFoodApp(Request $request, FoodApp $foodApp): RedirectResponse
    {
        $this->authorize('manageUsers');

        $foodApp->delete();

        return back()->with('success', 'Food delivery option removed.');
    }

    public function storeVoucher(StoreVoucherDealRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Voucher::create($validated);

        return back()->with('success', 'Voucher published.');
    }

    public function destroyVoucher(Request $request, Voucher $voucher): RedirectResponse
    {
        $this->authorize('manageUsers');

        $voucher->delete();

        return back()->with('success', 'Voucher deleted.');
    }
}
