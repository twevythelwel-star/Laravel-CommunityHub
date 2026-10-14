<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guidelines\StoreGuidelineRequest;
use App\Http\Requests\Guidelines\UpdateGuidelineRequest;
use App\Models\Guideline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/guidelines/page.tsx.
 *
 * The page always offered administrators Add, Edit and Delete, but this
 * controller only had `index`, so every change lived in React state and was
 * gone on reload. The write endpoints use `broadcastNotices`, the gate that
 * already governs the other estate-wide publications (notices, updates,
 * calendar events).
 */
class GuidelinesController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Guidelines', [
            // Grouped by category so the accordion renders without client-side grouping.
            'guidelines' => Guideline::ordered()
                ->get()
                ->groupBy('category')
                ->map(fn ($group) => $group->map(fn (Guideline $g) => [
                    'id' => $g->id,
                    'title' => $g->title,
                    'description' => $g->description,
                ])->values()),
            'canManage' => $request->user()->can('broadcastNotices'),
        ]);
    }

    public function store(StoreGuidelineRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Appended to the end of its category, which is where the page shows it.
        $validated['sort_order'] = (int) Guideline::where('category', $validated['category'])->max('sort_order') + 1;

        Guideline::create($validated);

        return back()->with('success', 'Guideline added.');
    }

    public function update(UpdateGuidelineRequest $request, Guideline $guideline): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['category'] !== $guideline->category) {
            $validated['sort_order'] = (int) Guideline::where('category', $validated['category'])->max('sort_order') + 1;
        }

        $guideline->update($validated);

        return back()->with('success', 'Guideline updated.');
    }

    public function destroy(Guideline $guideline): RedirectResponse
    {
        $guideline->delete();

        return back()->with('success', 'Guideline deleted.');
    }
}
