<?php

namespace App\Services\Queue;

use App\Jobs\Queue\DeliverWebhookJob;
use App\Jobs\Queue\DispatchQueuedNotificationJob;
use App\Jobs\Queue\ExportDataJob;
use App\Jobs\Queue\GeneratePdfDocumentJob;
use App\Jobs\Queue\GenerateReportJob;
use App\Jobs\Queue\ImportDataJob;
use App\Jobs\Queue\ProcessAiInferenceJob;
use App\Jobs\Queue\ProcessImageMediaJob;
use App\Jobs\Queue\ProcessQueuedEmailJob;
use App\Jobs\Queue\ProcessQueuedSmsJob;
use App\Jobs\Queue\SynchronizeExternalDataJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Horizon;
use Throwable;

class QueueMonitoringService
{
    /**
     * Get the 11 enterprise background processing job definitions.
     */
    public function getJobCatalog(): array
    {
        return [
            'email' => [
                'name' => 'Transactional & Community Email',
                'class' => ProcessQueuedEmailJob::class,
                'queue' => 'default',
                'priority' => 'normal',
                'retries' => 3,
                'timeout_seconds' => 60,
                'description' => 'Asynchronous resident invitations, security alerts, and payment receipts.',
            ],
            'sms' => [
                'name' => 'Priority SMS Dispatch',
                'class' => ProcessQueuedSmsJob::class,
                'queue' => 'high',
                'priority' => 'critical',
                'retries' => 3,
                'timeout_seconds' => 30,
                'description' => 'Time-critical visitor gate pass PINs, perimeter breach alerts, and 2FA codes.',
            ],
            'report' => [
                'name' => 'Heavy Analytics & Reconciliation Reports',
                'class' => GenerateReportJob::class,
                'queue' => 'low',
                'priority' => 'batch',
                'retries' => 2,
                'timeout_seconds' => 180,
                'description' => 'End-of-day multi-channel reconciliation, gate traffic metrics, and audit logs.',
            ],
            'export' => [
                'name' => 'Data Export Generator',
                'class' => ExportDataJob::class,
                'queue' => 'low',
                'priority' => 'batch',
                'retries' => 2,
                'timeout_seconds' => 300,
                'description' => 'Background generation of large CSV, Excel, and JSON resident directories.',
            ],
            'import' => [
                'name' => 'Bulk Data Ingestion & Validation',
                'class' => ImportDataJob::class,
                'queue' => 'low',
                'priority' => 'batch',
                'retries' => 2,
                'timeout_seconds' => 300,
                'description' => 'Chunked validation and ingestion of resident rosters, RFID tags, and vehicle passes.',
            ],
            'image' => [
                'name' => 'Image Processing & Thumbnailing',
                'class' => ProcessImageMediaJob::class,
                'queue' => 'default',
                'priority' => 'normal',
                'retries' => 3,
                'timeout_seconds' => 60,
                'description' => 'Avatar cropping, visitor photo badges, WebP compression, and media library scaling.',
            ],
            'pdf' => [
                'name' => 'Headless PDF Generation',
                'class' => GeneratePdfDocumentJob::class,
                'queue' => 'default',
                'priority' => 'normal',
                'retries' => 2,
                'timeout_seconds' => 90,
                'description' => 'DOMPDF compilation for printable visitor passes, dues invoices, and statements.',
            ],
            'notification' => [
                'name' => 'Multi-Channel Push & In-App Notifications',
                'class' => DispatchQueuedNotificationJob::class,
                'queue' => 'high',
                'priority' => 'critical',
                'retries' => 3,
                'timeout_seconds' => 45,
                'description' => 'Immediate fan-out across Firebase push, in-app toast feeds, and broadcast channels.',
            ],
            'ai' => [
                'name' => 'AI Processing & Threat Anomaly Inference',
                'class' => ProcessAiInferenceJob::class,
                'queue' => 'low',
                'priority' => 'batch',
                'retries' => 2,
                'timeout_seconds' => 120,
                'description' => 'Offloaded OCR gate license plate parsing, incident sentiment, and perimeter anomaly detection.',
            ],
            'sync' => [
                'name' => 'External Access Hardware & Ledger Sync',
                'class' => SynchronizeExternalDataJob::class,
                'queue' => 'low',
                'priority' => 'batch',
                'retries' => 3,
                'timeout_seconds' => 180,
                'description' => 'Two-way synchronization with physical gate controllers, barrier motors, and HOA ERP systems.',
            ],
            'webhook' => [
                'name' => 'Outgoing Signed Webhook Delivery',
                'class' => DeliverWebhookJob::class,
                'queue' => 'high',
                'priority' => 'critical',
                'retries' => 5,
                'timeout_seconds' => 30,
                'description' => 'HMAC-SHA256 signed payloads delivered to external endpoints with exponential retry backoff.',
            ],
        ];
    }

    /**
     * Dispatch one of the 11 queue jobs dynamically.
     */
    public function dispatchJob(string $type, array $payload = []): mixed
    {
        return match ($type) {
            'email' => ProcessQueuedEmailJob::dispatch(
                recipientEmail: $payload['recipient_email'] ?? 'resident@communityhub.io',
                subject: $payload['subject'] ?? 'Notice: Scheduled Community Maintenance Window',
                template: $payload['template'] ?? 'maintenance_notice',
                payload: $payload
            ),
            'sms' => ProcessQueuedSmsJob::dispatch(
                phoneNumber: $payload['phone_number'] ?? '+18765550199',
                message: $payload['message'] ?? 'Community Hub Gate 1 PIN: 849201. Valid for 2 hours.',
                metadata: $payload
            ),
            'report' => GenerateReportJob::dispatch(
                reportType: $payload['report_type'] ?? 'eod_reconciliation_summary',
                parameters: $payload['parameters'] ?? ['range' => '30d'],
                requestedByUserId: $payload['user_id'] ?? 1
            ),
            'export' => ExportDataJob::dispatch(
                dataset: $payload['dataset'] ?? 'gate_passes_audit',
                format: $payload['format'] ?? 'csv',
                userId: $payload['user_id'] ?? 1
            ),
            'import' => ImportDataJob::dispatch(
                importType: $payload['import_type'] ?? 'resident_roster_migration',
                recordsCount: (int) ($payload['records_count'] ?? 120),
                filePath: $payload['file_path'] ?? 'imports/roster_seed_2026.csv',
                userId: $payload['user_id'] ?? 1
            ),
            'image' => ProcessImageMediaJob::dispatch(
                imagePath: $payload['image_path'] ?? 'media/passes/badge_temp.png',
                conversions: $payload['conversions'] ?? ['thumbnail', 'webp_optimized'],
                ownerId: $payload['owner_id'] ?? 1
            ),
            'pdf' => GeneratePdfDocumentJob::dispatch(
                documentType: $payload['document_type'] ?? 'visitor_pass',
                entityId: (int) ($payload['entity_id'] ?? 892),
                recipientEmail: $payload['recipient_email'] ?? 'guest@example.com'
            ),
            'notification' => DispatchQueuedNotificationJob::dispatch(
                userId: (int) ($payload['user_id'] ?? 1),
                title: $payload['title'] ?? 'Access Granted: Visitor Checked In',
                body: $payload['body'] ?? 'Your visitor Marcus Vance Jr. checked in at Main Gate 1.',
                channels: $payload['channels'] ?? ['in_app', 'push', 'database']
            ),
            'ai' => ProcessAiInferenceJob::dispatch(
                taskType: $payload['task_type'] ?? 'license_plate_optical_recognition',
                inputPayload: $payload['input_payload'] ?? ['frame_id' => 'CAM-G1-094'],
                callbackChannel: $payload['callback_channel'] ?? 'presence-operations-center'
            ),
            'sync' => SynchronizeExternalDataJob::dispatch(
                targetSystem: $payload['target_system'] ?? 'hikvision_barrier_controller_hub',
                syncScope: $payload['sync_scope'] ?? 'delta',
                syncToken: $payload['sync_token'] ?? 'SYNC-'.now()->timestamp
            ),
            'webhook' => DeliverWebhookJob::dispatch(
                webhookUrl: $payload['webhook_url'] ?? 'https://api.partnerhoa.com/webhooks/gate-events',
                event: $payload['event'] ?? 'gatepass.cleared',
                payload: $payload['payload'] ?? ['pass_id' => 8492, 'timestamp' => now()->toISOString()],
                secretKey: $payload['secret_key'] ?? 'whsec_prod_enterprise_token'
            ),
            default => throw new \InvalidArgumentException("Unsupported queue job type [{$type}]"),
        };
    }

    /**
     * Get queue telemetry and depths across queues.
     */
    public function getQueueMetrics(): array
    {
        $defaultDriver = config('queue.default', 'database');
        $isRedis = $defaultDriver === 'redis';

        $highCount = $this->getQueueSize('high', $isRedis);
        $defaultCount = $this->getQueueSize('default', $isRedis);
        $lowCount = $this->getQueueSize('low', $isRedis);

        $failedCount = $this->getFailedJobsCount();

        return [
            'driver' => $defaultDriver,
            'is_redis' => $isRedis,
            'queues' => [
                'high' => [
                    'name' => 'high',
                    'count' => $highCount,
                    'priority' => 1,
                    'workers_allocated' => 6,
                    'jobs_handled' => ['SMS', 'Webhooks', 'Push Notifications'],
                ],
                'default' => [
                    'name' => 'default',
                    'count' => $defaultCount,
                    'priority' => 2,
                    'workers_allocated' => 4,
                    'jobs_handled' => ['Emails', 'PDF Generation', 'Image Optimization'],
                ],
                'low' => [
                    'name' => 'low',
                    'count' => $lowCount,
                    'priority' => 3,
                    'workers_allocated' => 2,
                    'jobs_handled' => ['Exports', 'Imports', 'Reports', 'AI Processing', 'Data Sync'],
                ],
            ],
            'total_pending' => $highCount + $defaultCount + $lowCount,
            'failed_jobs' => $failedCount,
            'status' => 'operational',
        ];
    }

    /**
     * Get current Laravel Horizon telemetry and supervisor status.
     */
    public function getHorizonStatus(): array
    {
        $isHorizonInstalled = class_exists(Horizon::class);
        $horizonStatus = 'inactive';
        $activeSupervisors = 0;
        $totalWorkers = 0;
        $redisPing = false;

        // Horizon only runs on a Redis queue. On any other driver there is no
        // Redis to ask, and asking anyway waits out a connection timeout
        // (seconds) on every call — every render of the queue and
        // observability pages.
        $usesRedis = config('queue.default') === 'redis';

        // Creating the connection object does not connect; ping to know.
        try {
            if ($usesRedis) {
                $redisPing = (bool) Redis::connection('default')->ping();
            }
        } catch (Throwable) {
            $redisPing = false;
        }

        if ($isHorizonInstalled && $redisPing) {
            try {
                $masterRepo = app(MasterSupervisorRepository::class);
                $masters = $masterRepo->all();
                if (! empty($masters)) {
                    $horizonStatus = 'running';
                    foreach ($masters as $master) {
                        $activeSupervisors += count($master->supervisors ?? []);
                    }
                }
            } catch (Throwable) {
                $horizonStatus = 'standby';
            }
        }

        return [
            'installed' => $isHorizonInstalled,
            'status' => $horizonStatus,
            'ui_path' => config('horizon.path', 'horizon'),
            'redis_connected' => $redisPing,
            'supervisors_count' => $activeSupervisors,
            'master_supervisors_configured' => count(config('horizon.defaults', [])),
            'environments' => array_keys(config('horizon.environments', [])),
            'architecture' => [
                'source' => 'Application (Web / API / Livewire)',
                'bus' => 'Laravel Queues (Illuminate\Contracts\Queue\ShouldQueue)',
                'transport' => 'Redis Memory Store (Cluster/Standalone)',
                'supervisor' => 'Laravel Horizon (Master Process + Supervisor)',
                'consumers' => 'Worker Pool (Multi-Process Scaled Workers)',
            ],
        ];
    }

    /**
     * Helper to read the size of a named queue.
     */
    protected function getQueueSize(string $queueName, bool $isRedis): int
    {
        try {
            if ($isRedis && extension_loaded('redis')) {
                return (int) Redis::connection('default')->llen("queues:{$queueName}");
            }

            return DB::table('jobs')->where('queue', $queueName)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Helper to get failed jobs count.
     */
    protected function getFailedJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
