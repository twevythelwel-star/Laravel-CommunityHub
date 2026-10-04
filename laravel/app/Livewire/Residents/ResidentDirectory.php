<?php

namespace App\Livewire\Residents;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ResidentDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'rq')]
    public string $search = '';

    #[Url(as: 'role')]
    public string $roleFilter = '';

    #[Url(as: 'rstatus')]
    public string $statusFilter = '';

    private const SORTABLE = ['name', 'lot'];

    #[Locked]
    public string $sortField = 'name';

    #[Locked]
    public string $sortDirection = 'asc';

    public int $perPage = 10;

    // Create Resident Modal State
    public bool $showCreateModal = false;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'Homeowner';

    public string $lot = '';

    public string $street = 'Palm Boulevard';

    // Profile Detail Modal State
    public bool $showProfileModal = false;

    public ?int $selectedUserId = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'role' => ['required', Rule::enum(UserRole::class)],
            'lot' => 'required|string|max:50',
            'street' => 'required|string|max:100',
        ];
    }

    public function mount(): void
    {
        $this->authorize('manageUsers');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
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
        $this->reset(['search', 'roleFilter', 'statusFilter']);
        $this->resetPage();
        $this->dispatch('notify', message: 'Directory filters reset.', type: 'info');
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->role = 'Homeowner';
        $this->lot = 'Lot '.rand(10, 99);
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function createResident(): void
    {
        $this->authorize('manageUsers');
        $this->validate();

        $userRole = UserRole::from($this->role);

        if ($userRole === UserRole::SystemAdmin && Auth::user()->role !== UserRole::SystemAdmin) {
            $this->addError('role', 'Only a System Admin can create another System Admin.');

            return;
        }

        $user = User::create([
            'uid' => (string) Str::uuid(),
            'name' => trim($this->name),
            'display_name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
            'phone' => trim($this->phone) ?: null,
            'role' => $userRole,
            'lot' => trim($this->lot),
            'street' => trim($this->street),
            'status' => 'Active',
            'password' => Hash::make(Str::random(16)),
        ]);

        $this->showCreateModal = false;
        $this->reset(['name', 'email', 'phone', 'lot']);

        $this->dispatch('notify', message: "Resident {$user->name} registered successfully at {$user->lot}!", type: 'success');
    }

    public function viewProfile(int $id): void
    {
        $this->selectedUserId = $id;
        $this->showProfileModal = true;
    }

    public function closeProfileModal(): void
    {
        $this->showProfileModal = false;
        $this->selectedUserId = null;
    }

    /**
     * Uses the statuses User::isActive() reads ('Active' / 'Inactive'), and
     * the same limits as DirectoryController::updateUser(): an Admin cannot
     * touch a System Admin, nobody deactivates themselves here, and a
     * deactivated account loses its device tokens.
     */
    public function toggleUserStatus(int $id): void
    {
        $this->authorize('manageUsers');

        $actor = Auth::user();
        $user = User::findOrFail($id);

        if ($user->role === UserRole::SystemAdmin && $actor->role !== UserRole::SystemAdmin) {
            $this->dispatch('notify', message: 'Only a System Admin can modify a System Admin account.', type: 'error');

            return;
        }

        if ($user->is($actor)) {
            $this->dispatch('notify', message: 'You cannot deactivate your own account here.', type: 'error');

            return;
        }

        $deactivating = $user->status === 'Active';

        $user->update($deactivating
            ? ['status' => 'Inactive', 'deactivated_at' => now()]
            : ['status' => 'Active', 'deactivated_at' => null]);

        if ($deactivating) {
            $user->tokens()->delete();
        }

        $action = $deactivating ? 'deactivated' : 'activated';
        $this->dispatch('notify', message: "Account for {$user->name} has been {$action}.", type: 'warning');
    }

    public function render(): View
    {
        $query = User::query()->withCount('gatePasses');

        if ($this->search !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('lot', 'like', $term)
                    ->orWhere('street', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        if ($this->roleFilter !== '') {
            $query->where('role', $this->roleFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        $residents = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $selectedUser = $this->selectedUserId ? User::with(['gatePasses' => fn ($q) => $q->latest()->limit(5)])->find($this->selectedUserId) : null;

        return view('livewire.residents.resident-directory', [
            'residents' => $residents,
            'selectedUser' => $selectedUser,
            'roles' => UserRole::cases(),
        ]);
    }
}
