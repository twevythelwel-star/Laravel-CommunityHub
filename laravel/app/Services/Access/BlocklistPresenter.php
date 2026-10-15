<?php

namespace App\Services\Access;

use App\Models\BlocklistEntry;
use App\Models\BlocklistRemovalRequest;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Collection;

/**
 * The props of the block list (Dashboard/BlockList).
 *
 * A blocklist is personal data and the stated reasons are unflattering, so a
 * resident's copy carries the name, status and date only. The reason, the
 * photo, who added the entry, the visitor directory and the pending removal
 * requests are for those who may manage the list.
 */
class BlocklistPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function props(User $viewer): array
    {
        $canManage = $viewer->can('manageBlocklist');

        return [
            'entries' => BlocklistEntry::with('removalRequests')
                ->latest('date_added')
                ->paginate(25)
                ->through(fn (BlocklistEntry $e) => $this->entry($e, $viewer, $canManage)),
            'visitors' => $canManage ? $this->knownVisitors() : [],
            'removalRequests' => $canManage ? $this->pendingRemovalRequests() : [],
            'can' => [
                'manage' => $canManage,
                'requestRemoval' => $viewer->role->isResident(),
            ],
        ];
    }

    /**
     * One row, redacted for anyone who cannot manage the list.
     *
     * @return array<string, mixed>
     */
    private function entry(BlocklistEntry $e, User $viewer, bool $canManage): array
    {
        $base = [
            'id' => $e->id,
            'name' => $e->name,
            'dateAdded' => $e->date_added->toIso8601String(),
            'expiryDate' => $e->expiry_date?->toIso8601String(),
            'isPermanent' => $e->isPermanent(),
            'inForce' => $e->isCurrentlyBlocking(),
            // Lets a resident see that they have already asked, so the action
            // can be disabled rather than silently doing nothing.
            'myRequestStatus' => $e->removalRequests->firstWhere('requested_by', $viewer->id)?->status,
        ];

        return $canManage ? [
            ...$base,
            'reason' => $e->reason,
            'photoUrl' => $e->photo_url,
            'addedBy' => $e->added_by,
            'pendingRequests' => $e->removalRequests->where('status', 'Pending')->count(),
        ] : $base;
    }

    /**
     * Known visitors, so the add form can pre-fill a name and reuse the ID
     * photo already on file. A directory of everyone's guests, so staff only.
     */
    private function knownVisitors(): Collection
    {
        return Visitor::query()
            ->orderBy('name')
            ->get(['id', 'name', 'id_image_url'])
            ->map(fn (Visitor $v) => ['id' => $v->id, 'name' => $v->name, 'idImageUrl' => $v->id_image_url]);
    }

    /** Pending resident requests, for the administrators who action them. */
    private function pendingRemovalRequests(): Collection
    {
        return BlocklistRemovalRequest::with(['entry:id,name', 'requester:id,display_name,lot'])
            ->pending()
            ->latest()
            ->get()
            ->map(fn (BlocklistRemovalRequest $r) => [
                'id' => $r->id,
                'entryId' => $r->blocklist_entry_id,
                'entryName' => $r->entry?->name,
                'requestedBy' => $r->requester?->display_name,
                'lot' => $r->requester?->lot,
                'reason' => $r->reason,
                'requestedAt' => $r->created_at->toIso8601String(),
            ]);
    }
}
