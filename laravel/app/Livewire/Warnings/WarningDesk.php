<?php

namespace App\Livewire\Warnings;

use App\Models\Warning;
use App\Models\WarningResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class WarningDesk extends Component
{
    use WithPagination;

    #[Url(as: 'wq')]
    public string $search = '';

    public string $sortField = 'issued_at';

    public string $sortDirection = 'desc';

    public int $perPage = 8;

    // Create Warning Form State
    public bool $showCreateModal = false;

    public string $title = '';

    public string $description = '';

    protected function rules(): array
    {
        return [
            'title' => 'required|string|min:4|max:120',
            'description' => 'required|string|min:10|max:1000',
        ];
    }

    public function mount(): void
    {
        abort_unless(Auth::user()?->isActive(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->title = '';
        $this->description = '';
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    /**
     * Any active account may raise an alert, as on /dashboard/warnings — and,
     * as there, three an hour at most, because each one reaches everybody.
     */
    public function createWarning(): void
    {
        abort_unless(Auth::user()?->isActive(), 403);
        $this->validate();

        $author = Auth::user();

        if (! RateLimiter::attempt('warning-desk:'.$author->id, 3, fn () => true, 3600)) {
            $this->dispatch('notify', message: 'You have raised several alerts recently. Please wait before raising another.', type: 'error');

            return;
        }

        $warning = Warning::create([
            'title' => trim($this->title),
            'description' => trim($this->description),
            'author_id' => $author->id,
            'author_name' => $author->name,
            'issued_at' => now(),
        ]);

        $this->showCreateModal = false;
        $this->reset(['title', 'description']);

        $this->dispatch('notify', message: "Community alert '{$warning->title}' has been broadcast to all residents!", type: 'warning');
    }

    public function vote(int $warningId, string $responseType): void
    {
        if (! in_array($responseType, ['confirmed', 'denied'], true)) {
            return;
        }

        abort_unless(Auth::user()?->isActive(), 403);
        $user = Auth::user();

        $warning = Warning::findOrFail($warningId);

        // An author cannot corroborate their own alert per business logic
        if ($warning->author_id === $user->id) {
            $this->dispatch('notify', message: 'You cannot vote on your own alert.', type: 'error');

            return;
        }

        WarningResponse::updateOrCreate(
            ['warning_id' => $warning->id, 'user_id' => $user->id],
            ['response' => $responseType]
        );

        $action = $responseType === 'confirmed' ? 'confirmed' : 'denied';
        $this->dispatch('notify', message: "You marked alert '{$warning->title}' as {$action}.", type: 'info');
    }

    /** Removing a false alarm is for the security desk, as on /dashboard/warnings. */
    public function deleteWarning(int $warningId): void
    {
        $this->authorize('manageSecurity');

        $warning = Warning::findOrFail($warningId);
        $title = $warning->title;
        $warning->delete();

        $this->dispatch('notify', message: "Alert '{$title}' removed from active feed.", type: 'info');
    }

    public function render(): View
    {
        $query = Warning::query()->with(['author', 'responses']);

        if ($this->search !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('author_name', 'like', $term);
            });
        }

        $warnings = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $currentUser = Auth::user();

        return view('livewire.warnings.warning-desk', [
            'warnings' => $warnings,
            'currentUser' => $currentUser,
            'totalAlerts' => Warning::count(),
        ]);
    }
}
