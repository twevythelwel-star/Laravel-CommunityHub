<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Backs src/app/dashboard/changelog/page.tsx. */
class ChangelogController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Changelog', [
            'entries' => ChangelogEntry::newestFirst()
                ->get()
                ->map(fn (ChangelogEntry $e) => [
                    'id' => $e->id,
                    'version' => $e->version,
                    'releasedOn' => $e->released_on->toDateString(),
                    'title' => $e->title,
                    'body' => $e->body,
                ]),
        ]);
    }
}
