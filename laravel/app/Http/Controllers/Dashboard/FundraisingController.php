<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Fundraiser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/fundraising/page.tsx, create-fundraiser-form.tsx and
 * donate-form.tsx.
 *
 * Amounts are accepted in major units from the form and stored in minor units,
 * so progress totals are exact integers rather than accumulated floats.
 */
class FundraisingController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Fundraising', [
            'fundraisers' => Fundraiser::with('donations')
                ->orderByRaw("FIELD(status, 'Active', 'Upcoming', 'Completed', 'Canceled')")
                ->get()
                ->map(fn (Fundraiser $f) => [
                    'id' => $f->id,
                    'title' => $f->title,
                    'description' => $f->description,
                    'goal' => $f->goal(),
                    'raised' => $f->raised(),
                    'progress' => $f->progressPercent(),
                    'donorCount' => $f->donorCount(),
                    'currency' => $f->goal_currency,
                    'startDate' => $f->start_date->toDateString(),
                    'endDate' => $f->end_date->toDateString(),
                    'status' => $f->status,
                    'isOpen' => $f->isOpen(),
                    'recentDonations' => $f->donations
                        ->sortByDesc('donated_at')
                        ->take(5)
                        ->map(fn ($d) => [
                            'donorName' => $d->publicDonorName(),
                            'amount' => $d->amount(),
                            'currency' => $d->currency,
                            'timestamp' => $d->donated_at->toIso8601String(),
                        ])->values(),
                ]),
            'canManage' => $request->user()->can('manageFundraisers'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'goal' => ['required', 'numeric', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', 'in:Active,Upcoming'],
        ]);

        Fundraiser::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'goal_minor' => (int) round($validated['goal'] * 100),
            'goal_currency' => 'JMD',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Fundraiser created.');
    }

    public function donate(Request $request, Fundraiser $fundraiser): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'in:JMD,USD,GBP,EUR,CAD'],
            'donor_name' => ['nullable', 'string', 'max:120'],
            'is_anonymous' => ['boolean'],
        ]);

        if (! $fundraiser->isOpen()) {
            return back()->withErrors(['amount' => 'This fundraiser is not currently accepting donations.']);
        }

        $user = $request->user();

        $fundraiser->donations()->create([
            'user_id' => $user->id,
            'amount_minor' => (int) round($validated['amount'] * 100),
            'currency' => $validated['currency'],
            'donor_name' => $validated['donor_name'] ?? $user->display_name,
            'is_anonymous' => $request->boolean('is_anonymous'),
            'donated_at' => now(),
        ]);

        return back()->with('success', 'Thank you for your donation.');
    }
}
