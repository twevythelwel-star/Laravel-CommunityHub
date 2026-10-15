<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Audit\AccessLogPresenter;
use App\Support\Csv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backs src/app/dashboard/access-log/page.tsx.
 *
 * The page listed five hardcoded entries with columns for user, role, method,
 * gate and timestamp — and no column for the outcome. Every mock row was a
 * successful entry, so a log whose purpose is security review showed only the
 * people who got in and never the ones who were refused. `result` and
 * `deny_reason` have always been recorded; nothing displayed them.
 *
 * The filters and the CSV export were written here and never reachable: the
 * page had no controls for either.
 */
class AccessLogController extends Controller
{
    private const FILTERS = ['gate', 'result', 'from', 'to'];

    /** See AccessLogPresenter for the table, the refusal count and the filter options. */
    public function index(Request $request, AccessLogPresenter $log): Response
    {
        return Inertia::render('Dashboard/AccessLog', $log->props($request->user(), $request->only(self::FILTERS)));
    }

    /**
     * CSV export, streamed so a long log does not exhaust memory, with the
     * same filters as the page: exporting the whole table while the user is
     * looking at a filtered view hands them a file that is not what they saw.
     */
    public function export(Request $request, AccessLogPresenter $log): StreamedResponse
    {
        $this->authorize('manageSecurity');

        return Csv::download(
            'access-log-'.now()->format('Y-m-d-His').'.csv',
            AccessLogPresenter::EXPORT_HEADER,
            $log->exportRows($request->user(), $request->only(self::FILTERS)),
        );
    }
}
