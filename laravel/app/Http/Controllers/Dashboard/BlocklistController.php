<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Visitors\ReviewBlocklistRemovalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blocklist\RequestBlocklistRemovalRequest;
use App\Http\Requests\Blocklist\ReviewBlocklistRemovalRequest;
use App\Http\Requests\Blocklist\StoreBlocklistEntryRequest;
use App\Http\Requests\Blocklist\UpdateBlocklistEntryRequest;
use App\Models\BlocklistEntry;
use App\Models\BlocklistRemovalRequest;
use App\Services\Access\BlocklistPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backs src/app/dashboard/block-list/page.tsx, blocklist-form.tsx and
 * request-removal-form.tsx.
 *
 * Access model, which the original could not settle:
 *
 *   The sidebar offered "Block List" to Homeowners and Temporary Homeowners, and
 *   the page carried a Homeowner-only "Request Removal" action — but the route
 *   required `manageBlocklist`, which residents do not hold. So the menu item led
 *   to a 403 and the resident feature was unreachable.
 *
 *   Resolved by splitting read from write: any signed-in resident may see that
 *   someone is blocked and ask for a review; only `manageBlocklist` may add,
 *   edit or remove entries.
 *
 * A blocklist is personal data; what a resident's copy withholds is set out in
 * BlocklistPresenter.
 */
class BlocklistController extends Controller
{
    /** See BlocklistPresenter for what residents and staff each receive. */
    public function index(Request $request, BlocklistPresenter $presenter): Response
    {
        return Inertia::render('Dashboard/BlockList', $presenter->props($request->user()));
    }

    public function store(StoreBlocklistEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        $entry = BlocklistEntry::create([
            ...$validated,
            'date_added' => now(),
            'added_by_id' => $user->id,
            'added_by' => $user->display_name,
        ]);

        Log::channel('security')->notice('Blocklist entry added', [
            'name' => $entry->name,
            'added_by' => $user->uid,
            'expires' => $entry->expiry_date?->toDateString() ?? 'never',
        ]);

        return back()->with('success', "{$entry->name} added to the blocklist.");
    }

    public function update(UpdateBlocklistEntryRequest $request, BlocklistEntry $blocklistEntry): RedirectResponse
    {
        $validated = $request->validated();

        $blocklistEntry->update($validated);

        $request->user()->recordActivity("Updated blocklist entry {$blocklistEntry->name}");

        return back()->with('success', 'Blocklist entry updated.');
    }

    public function destroy(Request $request, BlocklistEntry $blocklistEntry): RedirectResponse
    {
        $this->authorize('manageBlocklist');

        Log::channel('security')->notice('Blocklist entry removed', [
            'name' => $blocklistEntry->name,
            'removed_by' => $request->user()->uid,
        ]);

        $blocklistEntry->delete();

        return back()->with('success', 'Blocklist entry removed.');
    }

    // ── Removal requests ─────────────────────────────────────────────

    /**
     * A resident asks for an entry to be reviewed.
     *
     * Replaces a dialog that called console.log and told the resident the request
     * had been sent to administrators.
     */
    public function requestRemoval(RequestBlocklistRemovalRequest $request, BlocklistEntry $blocklistEntry): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // One open request per resident per entry; asking again reopens the
        // existing row rather than stacking duplicates.
        BlocklistRemovalRequest::updateOrCreate(
            [
                'blocklist_entry_id' => $blocklistEntry->id,
                'requested_by' => $user->id,
            ],
            [
                'reason' => $validated['reason'],
                'status' => 'Pending',
                'reviewer_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );

        $user->recordActivity("Requested blocklist review for {$blocklistEntry->name}");

        return back()->with('success', 'Your request has been sent to community administration for review.');
    }

    /** An administrator approves or declines a request. */
    public function reviewRemoval(ReviewBlocklistRemovalRequest $request, BlocklistRemovalRequest $removalRequest, ReviewBlocklistRemovalAction $review): RedirectResponse
    {
        $removed = $review->execute($removalRequest, $request->user(), $request->validated());

        return back()->with('success', $removed ? 'Request approved and the entry removed from the blocklist.' : 'Request declined.');
    }
}
