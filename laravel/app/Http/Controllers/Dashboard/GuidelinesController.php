<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Guideline;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Backs src/app/dashboard/guidelines/page.tsx. */
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
        ]);
    }
}
