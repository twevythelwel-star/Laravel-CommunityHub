<?php

namespace App\Livewire\Filament;

use App\Enums\GateId;
use App\Enums\PassCategory;
use App\Enums\PassStatus;
use App\Enums\UserRole;
use App\Filament\Core\Notifications\Notification;
use App\Filament\Core\PanelRegistry;
use App\Filament\Resources\GatePassResource;
use App\Filament\Resources\ResidentResource;
use App\Filament\Resources\WarningResource;
use App\Filament\Widgets\EstateStatsOverviewWidget;
use App\Models\GatePass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.livewire')]
class FilamentHub extends Component
{
    use WithPagination;

    #[Url(as: 'panel')]
    public string $currentPanel = 'admin'; // 'admin' or 'portal'

    #[Url(as: 'view')]
    public string $currentView = 'dashboard'; // 'dashboard', 'passes', 'residents', 'warnings', 'infolist', 'rbac'

    // RBAC Simulation Persona
    public string $simulatedRole = 'Admin';

    public string $simulatedStatus = 'active';

    // Pass Search & Filters
    public string $search = '';

    public string $statusFilter = '';

    public string $categoryFilter = '';

    public int $perPage = 8;

    // Form Demo State (GatePass CRUD)
    public bool $showCreateModal = false;

    public string $formHolderName = '';

    public string $formCategory = 'VISITOR';

    public string $formGate = 'GATE-01';

    public string $formProperty = '';

    public string $formValidFrom = '';

    public string $formValidUntil = '';

    public bool $formSingleEntry = true;

    // Infolist Modal State
    public bool $showInfolistModal = false;

    public ?GatePass $infolistRecord = null;

    /**
     * Every panel here lists and acts on the estate's passes, so it is the
     * security desk's; actions re-check because mount() runs only once.
     */
    public function mount(): void
    {
        $this->authorize('manageSecurity');

        $this->formValidFrom = now()->format('Y-m-d\TH:i');
        $this->formValidUntil = now()->addDays(2)->format('Y-m-d\TH:i');
        $this->formProperty = 'Lot 14, Royal Palm';
    }

    public function switchPanel(string $panelId): void
    {
        if (in_array($panelId, ['admin', 'portal'], true)) {
            $this->currentPanel = $panelId;
            $this->currentView = 'dashboard';
            $this->dispatch('notify', message: 'Switched to Filament '.ucfirst($panelId).' Panel', type: 'info');
        }
    }

    public function setView(string $view): void
    {
        $this->currentView = $view;
    }

    public function openCreateModal(): void
    {
        $this->formHolderName = '';
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function savePass(): void
    {
        $this->authorize('manageSecurity');

        $this->validate([
            'formHolderName' => 'required|min:2',
            'formProperty' => 'required',
        ]);

        $cat = PassCategory::tryFrom($this->formCategory) ?? PassCategory::Visitor;
        $gate = GateId::tryFrom($this->formGate) ?? GateId::Gate01;
        $user = Auth::user();

        $pass = GatePass::create([
            'pass_id' => $cat->passIdPrefix().'-'.strtoupper(Str::random(8)),
            'user_id' => $user->id,
            'category' => $cat,
            'holder_name' => trim($this->formHolderName),
            'property' => trim($this->formProperty),
            'access_zone' => 'ZONE-HOST-RESIDENCE',
            'designated_gate' => $gate,
            'valid_from' => Carbon::parse($this->formValidFrom),
            'valid_until' => Carbon::parse($this->formValidUntil),
            'single_entry' => $this->formSingleEntry,
            'color_variant' => 'blue',
            'rotation_seq' => 1,
            'status' => PassStatus::Active,
        ]);

        $this->showCreateModal = false;

        Notification::make()
            ->title('Gate Pass Issued')
            ->body("Pass #{$pass->pass_id} registered for {$pass->holder_name}")
            ->success()
            ->send();

        $this->dispatch('notify', message: "Filament Action: Pass #{$pass->pass_id} created successfully!", type: 'success');
    }

    public function triggerAction(string $actionName, int $recordId): void
    {
        $this->authorize('manageSecurity');

        $pass = GatePass::findOrFail($recordId);

        if ($actionName === 'checkIn') {
            $pass->status = PassStatus::CheckedIn;
            $pass->checked_in_at = now();
            $pass->save();
            Notification::make()->title('Visitor Checked In')->body("Visitor {$pass->holder_name} admitted at gate.")->success()->send();
            $this->dispatch('notify', message: "Filament Action: Checked in {$pass->holder_name}", type: 'success');
        } elseif ($actionName === 'checkOut') {
            $pass->status = PassStatus::CheckedOut;
            $pass->checked_out_at = now();
            $pass->save();
            Notification::make()->title('Visitor Checked Out')->body("Visitor {$pass->holder_name} exited perimeter.")->info()->send();
            $this->dispatch('notify', message: "Filament Action: Checked out {$pass->holder_name}", type: 'info');
        } elseif ($actionName === 'revoke') {
            $pass->status = PassStatus::Revoked;
            $pass->revoked_at = now();
            $pass->save();
            Notification::make()->title('Credential Revoked')->body("Pass #{$pass->pass_id} has been revoked.")->danger()->send();
            $this->dispatch('notify', message: "Filament Action: Revoked pass #{$pass->pass_id}", type: 'error');
        }
    }

    public function viewInfolist(int $recordId): void
    {
        $this->infolistRecord = GatePass::find($recordId);
        $this->showInfolistModal = true;
    }

    public function closeInfolist(): void
    {
        $this->showInfolistModal = false;
        $this->infolistRecord = null;
    }

    public function sendSampleNotification(string $type): void
    {
        $notif = Notification::make();
        if ($type === 'success') {
            $notif->title('Operation Succeeded')->body('Filament table export completed successfully.')->success()->send();
            $this->dispatch('notify', message: 'Filament Notification: Operation Succeeded!', type: 'success');
        } elseif ($type === 'warning') {
            $notif->title('Security Caution')->body('Unrecognized license plate observed near perimeter.')->warning()->send();
            $this->dispatch('notify', message: 'Filament Notification: Security Warning Triggered', type: 'warning');
        } else {
            $notif->title('Access Denied')->body('Account does not hold necessary policy permissions.')->danger()->send();
            $this->dispatch('notify', message: 'Filament Notification: Policy Denied', type: 'error');
        }
    }

    public function render(): View
    {
        $panels = PanelRegistry::all();
        $adminPanel = PanelRegistry::get('admin');
        $portalPanel = PanelRegistry::get('portal');

        $widget = new EstateStatsOverviewWidget;
        $stats = $widget->renderStats();

        // Simulated User for Authorization Testing
        $dummyUser = new User([
            'role' => UserRole::tryFrom($this->simulatedRole) ?? UserRole::Admin,
            'status' => $this->simulatedStatus,
        ]);
        $canAccessAdmin = $dummyUser->canAccessFilamentPanel('admin');
        $canAccessPortal = $dummyUser->canAccessFilamentPanel('portal');

        // Query passes
        $query = GatePass::query();
        if ($this->search !== '') {
            $t = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($t) {
                $q->where('holder_name', 'like', $t)
                    ->orWhere('pass_id', 'like', $t)
                    ->orWhere('property', 'like', $t);
            });
        }
        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }
        if ($this->categoryFilter !== '') {
            $query->where('category', $this->categoryFilter);
        }

        $passes = $query->latest()->paginate($this->perPage);

        return view('livewire.filament.filament-hub', [
            'panels' => $panels,
            'adminPanel' => $adminPanel,
            'portalPanel' => $portalPanel,
            'stats' => $stats,
            'passes' => $passes,
            'canAccessAdmin' => $canAccessAdmin,
            'canAccessPortal' => $canAccessPortal,
            'gatePassResource' => GatePassResource::class,
            'residentResource' => ResidentResource::class,
            'warningResource' => WarningResource::class,
            'userRoles' => UserRole::cases(),
        ]);
    }
}
