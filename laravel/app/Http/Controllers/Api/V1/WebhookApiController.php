<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateWebhookSubscriptionApiRequest;
use App\Http\Requests\Api\Webhooks\TestWebhookSubscriptionApiRequest;
use App\Http\Resources\WebhookSubscriptionResource;
use App\Http\Responses\ApiResponse;
use App\Models\WebhookSubscription;
use App\Services\Webhooks\IncomingWebhookVerifier;
use App\Services\Webhooks\WebhookDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookApiController extends Controller
{
    /**
     * Subscriptions send estate events to outside URLs: an integration
     * setting, and WebhookDispatcherService::dispatch() delivers to every
     * active subscription whoever owns it. So they are the System Admin's
     * (`operatePlatform`). incoming() stays public; it verifies a signature.
     */
    public function __construct(
        protected WebhookDispatcherService $dispatcher
    ) {
        $this->middleware(['auth:sanctum', 'active', 'can:operatePlatform'])->except('incoming');
    }

    /**
     * List all webhook subscriptions owned by the authenticated user / estate.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = WebhookSubscription::query()
            ->where('user_id', $user->id);

        if ($request->has('estate_id')) {
            $query->where('estate_id', $request->query('estate_id'));
        }

        $subscriptions = $query->latest()->get();

        return ApiResponse::success(
            WebhookSubscriptionResource::collection($subscriptions),
            'Webhook subscriptions retrieved successfully.'
        );
    }

    /**
     * Create a new webhook subscription.
     */
    public function store(CreateWebhookSubscriptionApiRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $secret = $validated['secret'] ?? WebhookSubscription::generateSecret();

        $subscription = WebhookSubscription::create([
            'user_id' => $user->id,
            'estate_id' => $request->input('estate_id', $user->estate_id ?? null),
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $secret,
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return ApiResponse::success(
            [
                'subscription' => new WebhookSubscriptionResource($subscription),
                'signingSecret' => $secret, // Disclosed upon creation for caller to verify incoming signatures
            ],
            'Webhook subscription created successfully.',
            201
        );
    }

    /**
     * Get a specific webhook subscription.
     */
    public function show(Request $request, WebhookSubscription $subscription): JsonResponse
    {
        $this->authorize('manage', $subscription);

        return ApiResponse::success(
            new WebhookSubscriptionResource($subscription),
            'Webhook subscription retrieved successfully.'
        );
    }

    /**
     * Delete a webhook subscription.
     */
    public function destroy(Request $request, WebhookSubscription $subscription): JsonResponse
    {
        $this->authorize('manage', $subscription);

        $subscription->delete();

        return ApiResponse::success(null, 'Webhook subscription deleted successfully.');
    }

    /**
     * Send a test ping event to the subscribed webhook endpoint.
     */
    public function test(TestWebhookSubscriptionApiRequest $request, WebhookSubscription $subscription): JsonResponse
    {
        $this->authorize('manage', $subscription);

        $result = $this->dispatcher->dispatchDirect(
            $subscription,
            'webhook.test',
            [
                'ping' => 'pong',
                'timestamp' => now()->toIso8601String(),
                'message' => 'Community Hub Webhook Verification Test Event',
            ]
        );

        return ApiResponse::success([
            'delivery_id' => $result->id,
            'status_code' => $result->status_code,
            'is_success' => (bool) $result->is_success,
            'duration_ms' => $result->duration_ms,
        ], 'Webhook test event dispatched.');
    }

    /**
     * Handle incoming webhooks from external providers with signature
     * verification (IncomingWebhookVerifier). A service with no secret
     * configured is refused rather than accepted unchecked.
     */
    public function incoming(Request $request, string $service, IncomingWebhookVerifier $verifier): JsonResponse
    {
        if (! $verifier->isConfigured($service)) {
            return ApiResponse::error("Webhooks from [{$service}] are not configured.", 'NOT_CONFIGURED', 404);
        }

        if (! $verifier->verify($request, $service)) {
            Log::warning("Incoming webhook signature verification failed for service [{$service}].");

            return ApiResponse::error('Invalid webhook signature.', 'INVALID_SIGNATURE', 401);
        }

        // Not the headers: they carry the sender's credentials and signature.
        Log::info("Incoming webhook received from [{$service}]:", [
            'event' => $request->input('event') ?? $request->input('type') ?? 'unknown',
        ]);

        return ApiResponse::success(
            ['received' => true, 'service' => $service, 'timestamp' => now()->toIso8601String()],
            "Webhook for {$service} acknowledged successfully."
        );
    }
}
