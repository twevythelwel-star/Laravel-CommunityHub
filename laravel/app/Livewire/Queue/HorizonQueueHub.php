<?php

namespace App\Livewire\Queue;

use App\Services\Queue\QueueMonitoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HorizonQueueHub extends Component
{
    /**
     * System Admin only: this queues real email, SMS, export and webhook
     * jobs. boot() runs on the first load and on every action request.
     */
    public function boot(): void
    {
        $this->authorize('operatePlatform');
    }

    public string $activeTab = 'telemetry'; // telemetry, dispatcher, catalog, architecture

    // Dispatcher form state
    public string $selectedJobType = 'email';

    public string $customPayloadJson = '';

    public ?string $feedbackMessage = null;

    // Dispatched job audit log history for session
    public array $jobHistory = [];

    public function mount(QueueMonitoringService $service): void
    {
        $this->updateDefaultPayload($this->selectedJobType);

        // Seed initial history item for demonstration
        $this->jobHistory = [
            [
                'id' => 'JOB-Q-8901',
                'type' => 'email',
                'name' => 'Transactional & Community Email',
                'queue' => 'default',
                'priority' => 'normal',
                'status' => 'processed',
                'dispatched_by' => 'Alexander Wright (Admin)',
                'dispatched_at' => now()->subMinutes(8)->format('H:i:s'),
                'details' => 'Recipient: resident.roster@solarisbay.io | Subject: Gate 1 Maintenance',
            ],
            [
                'id' => 'JOB-Q-8902',
                'type' => 'sms',
                'name' => 'Priority SMS Dispatch',
                'queue' => 'high',
                'priority' => 'critical',
                'status' => 'processed',
                'dispatched_by' => 'Gatehouse Scanner Kiosk',
                'dispatched_at' => now()->subMinutes(3)->format('H:i:s'),
                'details' => 'Destination: +18765550199 | PIN: 849201',
            ],
        ];
    }

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function updatedSelectedJobType(string $value): void
    {
        $this->updateDefaultPayload($value);
    }

    public function updateDefaultPayload(string $type): void
    {
        $defaults = [
            'email' => ['recipient_email' => 'alexander.wright@solarisbay.io', 'subject' => 'Notice: Annual General Meeting & Financial Audit', 'template' => 'agm_notice'],
            'sms' => ['phone_number' => '+18765550199', 'message' => 'PIN: 394821 - Express clearance at East Gate 2. Valid today.', 'metadata' => ['pass_id' => 492]],
            'report' => ['report_type' => 'eod_triparty_reconciliation', 'parameters' => ['date' => now()->format('Y-m-d'), 'include_audit_trail' => true]],
            'export' => ['dataset' => 'resident_directory_master', 'format' => 'csv', 'filters' => ['status' => 'active']],
            'import' => ['import_type' => 'rfid_vehicle_tags_batch', 'records_count' => 250, 'file_path' => 'storage/imports/rfid_batch_oct.csv'],
            'image' => ['image_path' => 'public/assets/residents/avatar_891.jpg', 'conversions' => ['thumbnail_256', 'webp_optimized', 'badge_print']],
            'pdf' => ['document_type' => 'monthly_hoa_dues_invoice', 'entity_id' => 1042, 'recipient_email' => 'owner@solarisbay.io'],
            'notification' => ['title' => 'Perimeter Breach Detected', 'body' => 'Sector 4 optical beam triggered. Gatehouse responding.', 'channels' => ['in_app', 'push', 'database']],
            'ai' => ['task_type' => 'optical_license_plate_anonymizer', 'input_payload' => ['camera' => 'G1_CAM_INBOUND', 'frame_rate' => 30]],
            'sync' => ['target_system' => 'hikvision_barrier_controller_v2', 'sync_scope' => 'rfid_credentials_sync', 'sync_token' => 'TKN-SYNC-894'],
            'webhook' => ['webhook_url' => 'https://security-vendor.example.org/api/webhooks/gate', 'event' => 'gatepass.revoked', 'payload' => ['pass_id' => 'GP-8492', 'reason' => 'visitor_denied']],
        ];

        $payload = $defaults[$type] ?? ['action' => 'process_queue_item'];
        $this->customPayloadJson = json_encode($payload, JSON_PRETTY_PRINT);
    }

    public function dispatchSelectedJob(QueueMonitoringService $service): void
    {
        $payload = json_decode($this->customPayloadJson, true) ?? [];
        $payload['user_id'] = Auth::id();

        $service->dispatchJob($this->selectedJobType, $payload);

        $catalog = $service->getJobCatalog();
        $jobInfo = $catalog[$this->selectedJobType] ?? [];
        $jobName = $jobInfo['name'] ?? $this->selectedJobType;
        $targetQueue = $jobInfo['queue'] ?? 'default';

        $operator = Auth::user()->name;

        array_unshift($this->jobHistory, [
            'id' => 'JOB-Q-'.rand(1000, 9999),
            'type' => $this->selectedJobType,
            'name' => $jobName,
            'queue' => $targetQueue,
            'priority' => $jobInfo['priority'] ?? 'normal',
            'status' => 'queued',
            'dispatched_by' => $operator,
            'dispatched_at' => now()->format('H:i:s'),
            'details' => substr(json_encode($payload), 0, 75).'...',
        ]);

        if (count($this->jobHistory) > 12) {
            array_pop($this->jobHistory);
        }

        $this->feedbackMessage = "Job [{$jobName}] successfully queued onto [{$targetQueue}] priority pool!";
    }

    public function render(QueueMonitoringService $service): View
    {
        $metrics = $service->getQueueMetrics();
        $horizon = $service->getHorizonStatus();
        $catalog = $service->getJobCatalog();

        return view('livewire.queue.horizon-queue-hub', [
            'metrics' => $metrics,
            'horizon' => $horizon,
            'catalog' => $catalog,
        ])->layout('layouts.app');
    }
}
