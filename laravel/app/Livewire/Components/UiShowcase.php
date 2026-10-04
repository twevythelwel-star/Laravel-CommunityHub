<?php

namespace App\Livewire\Components;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class UiShowcase extends Component
{
    // Interactive Form Sandbox State
    public string $formName = '';

    public string $formEmail = '';

    public string $formCategory = 'General Inquiry';

    public string $formPriority = 'Normal';

    public string $formNotes = '';

    public bool $formAccepted = false;

    public bool $isFormSubmitted = false;

    // Interactive Modal Sandbox State
    public bool $showDemoModal = false;

    public string $modalContent = 'This modal is powered by Livewire state wire:model synchronization and Alpine.js transitions.';

    // Interactive Table Sandbox State
    public string $tableSearch = '';

    public array $selectedRows = [];

    public bool $selectAll = false;

    // Alpine counter
    public int $liveCounter = 42;

    protected function rules(): array
    {
        return [
            'formName' => 'required|min:3',
            'formEmail' => 'required|email',
            'formCategory' => 'required',
            'formPriority' => 'required',
            'formNotes' => 'nullable|max:250',
            'formAccepted' => 'accepted',
        ];
    }

    public function submitDemoForm(): void
    {
        $this->validate();
        $this->isFormSubmitted = true;
        $this->dispatch('notify', message: 'Reactive Form submitted and validated in real time!', type: 'success');
    }

    public function resetDemoForm(): void
    {
        $this->reset(['formName', 'formEmail', 'formNotes', 'formAccepted', 'isFormSubmitted']);
        $this->resetValidation();
        $this->dispatch('notify', message: 'Demo form state cleared.', type: 'info');
    }

    public function toggleSelectAll(): void
    {
        $allIds = [101, 102, 103, 104, 105];
        if ($this->selectAll) {
            $this->selectedRows = $allIds;
        } else {
            $this->selectedRows = [];
        }
    }

    public function performBulkAction(string $action): void
    {
        $count = count($this->selectedRows);
        if ($count === 0) {
            $this->dispatch('notify', message: 'Please select at least one row first.', type: 'warning');

            return;
        }

        $this->dispatch('notify', message: "Bulk action '{$action}' applied to {$count} items!", type: 'success');
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    public function incrementCounter(): void
    {
        $this->liveCounter++;
    }

    public function decrementCounter(): void
    {
        $this->liveCounter--;
    }

    public function render(): View
    {
        $sampleItems = collect([
            ['id' => 101, 'name' => 'Main Gate Barrier Sensor', 'type' => 'Hardware', 'status' => 'Operational', 'latency' => '12ms', 'updated' => '2m ago'],
            ['id' => 102, 'name' => 'ANPR Camera Lane 1', 'type' => 'Camera', 'status' => 'Operational', 'latency' => '45ms', 'updated' => 'Just now'],
            ['id' => 103, 'name' => 'Pedestrian Turnstile North', 'type' => 'Turnstile', 'status' => 'Maintenance', 'latency' => '180ms', 'updated' => '1h ago'],
            ['id' => 104, 'name' => 'Service Gate RFID Scanner', 'type' => 'RFID', 'status' => 'Operational', 'latency' => '19ms', 'updated' => '5m ago'],
            ['id' => 105, 'name' => 'Perimeter Lidar Beacon 04', 'type' => 'Telemetry', 'status' => 'Warning', 'latency' => '320ms', 'updated' => '12m ago'],
        ]);

        if ($this->tableSearch !== '') {
            $term = strtolower(trim($this->tableSearch));
            $sampleItems = $sampleItems->filter(function ($item) use ($term) {
                return str_contains(strtolower($item['name']), $term)
                    || str_contains(strtolower($item['type']), $term)
                    || str_contains(strtolower($item['status']), $term);
            });
        }

        return view('livewire.components.ui-showcase', [
            'sampleItems' => $sampleItems,
        ]);
    }
}
