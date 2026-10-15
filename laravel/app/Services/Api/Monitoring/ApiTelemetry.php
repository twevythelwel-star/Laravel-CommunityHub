<?php

declare(strict_types=1);

namespace App\Services\Api\Monitoring;

use App\Models\ApiRequestLog;
use App\Models\WebhookDeliveryLog;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Request volume, latency and status mix, token count and webhook delivery
 * rate over the last 24 hours, for /api/v1/metrics.
 */
class ApiTelemetry
{
    /**
     * @return array<string, mixed>
     */
    public function lastDay(): array
    {
        $since = now()->subHours(24);

        return [
            'period' => '24_hours',
            'api' => $this->requests($since),
            'tokens' => [
                'active_count' => PersonalAccessToken::count(),
            ],
            'webhooks' => $this->webhooks($since),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requests(\DateTimeInterface $since): array
    {
        $logs = fn () => ApiRequestLog::where('created_at', '>=', $since);

        $statusCounts = $logs()
            ->selectRaw('
                COUNT(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 END) as success_2xx,
                COUNT(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 END) as client_error_4xx,
                COUNT(CASE WHEN status_code >= 500 THEN 1 END) as server_error_5xx
            ')
            ->first();

        return [
            'total_requests' => $logs()->count(),
            'average_latency_ms' => round((float) $logs()->avg('duration_ms'), 2),
            'status_breakdown' => [
                '2xx_success' => (int) ($statusCounts->success_2xx ?? 0),
                '4xx_client_error' => (int) ($statusCounts->client_error_4xx ?? 0),
                '5xx_server_error' => (int) ($statusCounts->server_error_5xx ?? 0),
            ],
        ];
    }

    /**
     * @return array{total_deliveries: int, successful_deliveries: int, success_rate_percent: float}
     */
    private function webhooks(\DateTimeInterface $since): array
    {
        $total = WebhookDeliveryLog::where('created_at', '>=', $since)->count();
        $successful = WebhookDeliveryLog::where('created_at', '>=', $since)->where('is_success', true)->count();

        return [
            'total_deliveries' => $total,
            'successful_deliveries' => $successful,
            'success_rate_percent' => $total > 0 ? round(($successful / $total) * 100, 2) : 100.0,
        ];
    }
}
