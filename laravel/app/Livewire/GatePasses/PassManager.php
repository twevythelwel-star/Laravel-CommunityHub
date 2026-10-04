<?php

namespace App\Livewire\GatePasses;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Models\GatePass;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PassManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'cat')]
    public string $categoryFilter = '';

    #[Url(as: 'gate')]
    public string $gateFilter = '';

    private const SORTABLE = ['created_at', 'pass_id', 'holder_name', 'valid_until', 'status'];

    #[Locked]
    public string $sortField = 'created_at';

    #[Locked]
    public string $sortDirection = 'desc';

    public int $perPage = 10;

    // Create Pass Form State
    public bool $showCreateModal = false;

    public string $holder_name = '';

    public string $category = 'VISITOR';

    public string $designated_gate = 'GATE-ANY';

    public string $property = '';

    public string $access_zone = 'ZONE-HOST-RESIDENCE';

    public string $valid_from = '';

    public string $valid_until = '';

    public bool $single_entry = true;

    public string $color_variant = 'blue';

    // Detail & QR Modal State
    public bool $showDetailModal = false;

    public ?int $selectedPassId = null;

    // Revocation Modal State
    public bool $showRevokeModal = false;

    public ?int $revokePassId = null;

    public string $revocationReason = '';

    protected function rules(): array
    {
        return [
            'holder_name' => 'required|string|min:2|max:100',
            'category' => 'required|string',
            'designated_gate' => 'required|string',
            'property' => 'required|string|max:120',
            'access_zone' => 'required|string|max:80',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'single_entry' => 'boolean',
        ];
    }

    /**
     * The security desk's register of every pass in the estate. Each action
     * re-checks the gate as well, because Livewire runs mount() only on the
     * first load and actions arrive later as separate requests.
     */
    public function mount(): void
    {
        $this->authorize('manageSecurity');

        $this->valid_from = now()->format('Y-m-d\TH:i');
        $this->valid_until = now()->addDays(2)->format('Y-m-d\TH:i');
        $this->property = 'Residence Lot 12, Boulevard';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingGateFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'categoryFilter', 'gateFilter']);
        $this->resetPage();
        $this->dispatch('notify', message: 'Filters reset to default view.', type: 'info');
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->holder_name = '';
        $this->valid_from = now()->format('Y-m-d\TH:i');
        $this->valid_until = now()->addDays(2)->format('Y-m-d\TH:i');
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function createPass(): void
    {
        $this->authorize('manageSecurity');
        $this->validate();

        $passCategory = PassCategory::tryFrom($this->category) ?? PassCategory::Visitor;
        $gate = GateId::tryFrom($this->designated_gate) ?? GateId::Any;

        $issuer = Auth::user();

        $passId = $passCategory->passIdPrefix().'-'.strtoupper(Str::random(8));

        $gatePass = GatePass::create([
            'pass_id' => $passId,
            'user_id' => $issuer->id,
            'category' => $passCategory,
            'holder_name' => trim($this->holder_name),
            'property' => trim($this->property),
            'access_zone' => trim($this->access_zone),
            'designated_gate' => $gate,
            'valid_from' => Carbon::parse($this->valid_from),
            'valid_until' => Carbon::parse($this->valid_until),
            'single_entry' => $this->single_entry,
            'color_variant' => $this->color_variant,
            'rotation_seq' => 1,
            'status' => PassStatus::Active,
        ]);

        $this->showCreateModal = false;
        $this->reset(['holder_name']);

        $this->dispatch('notify', message: "Gate pass #{$gatePass->pass_id} issued successfully for {$gatePass->holder_name}!", type: 'success');
    }

    public function viewPass(int $id): void
    {
        $this->selectedPassId = $id;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedPassId = null;
    }

    public function checkIn(int $id): void
    {
        $this->authorize('manageSecurity');

        $pass = GatePass::findOrFail($id);

        if ($pass->status !== PassStatus::Active && $pass->status !== PassStatus::Issued) {
            $this->dispatch('notify', message: "Pass {$pass->pass_id} cannot be checked in from {$pass->status->label()}.", type: 'warning');

            return;
        }

        $pass->status = PassStatus::CheckedIn;
        $pass->checked_in_at = now();
        $pass->save();

        $this->dispatch('notify', message: "Visitor {$pass->holder_name} checked in at Gate.", type: 'success');
    }

    public function checkOut(int $id): void
    {
        $this->authorize('manageSecurity');

        $pass = GatePass::findOrFail($id);

        if ($pass->status !== PassStatus::CheckedIn) {
            $this->dispatch('notify', message: "Pass {$pass->pass_id} is not checked in.", type: 'warning');

            return;
        }

        $pass->status = PassStatus::CheckedOut;
        $pass->checked_out_at = now();
        $pass->save();

        $this->dispatch('notify', message: "Visitor {$pass->holder_name} checked out.", type: 'info');
    }

    public function confirmRevoke(int $id): void
    {
        $this->revokePassId = $id;
        $this->revocationReason = 'Security request';
        $this->showRevokeModal = true;
    }

    public function closeRevokeModal(): void
    {
        $this->showRevokeModal = false;
        $this->revokePassId = null;
    }

    public function revokePass(): void
    {
        $this->authorize('manageSecurity');

        if (! $this->revokePassId) {
            return;
        }

        $pass = GatePass::findOrFail($this->revokePassId);
        $pass->status = PassStatus::Revoked;
        $pass->revoked_at = now();
        $pass->revocation_reason = $this->revocationReason ?: 'Administrative revocation';
        $pass->save();

        $this->showRevokeModal = false;
        $this->revokePassId = null;
        $this->dispatch('notify', message: "Gate Pass #{$pass->pass_id} has been revoked.", type: 'error');
    }

    public function render(): View
    {
        $query = GatePass::query();

        if ($this->search !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('holder_name', 'like', $term)
                    ->orWhere('pass_id', 'like', $term)
                    ->orWhere('property', 'like', $term);
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->categoryFilter !== '') {
            $query->where('category', $this->categoryFilter);
        }

        if ($this->gateFilter !== '') {
            $query->where('designated_gate', $this->gateFilter);
        }

        $passes = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $selectedPass = $this->selectedPassId ? GatePass::find($this->selectedPassId) : null;

        return view('livewire.gate-passes.pass-manager', [
            'passes' => $passes,
            'selectedPass' => $selectedPass,
            'categories' => PassCategory::cases(),
            'gates' => GateId::cases(),
            'statuses' => PassStatus::cases(),
        ]);
    }
}
