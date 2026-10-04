<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Payments\Modular\DTOs\CheckoutSessionRequest;
use App\Services\Payments\Modular\DTOs\CustomerPortalRequest;
use App\Services\Payments\Modular\DTOs\SubscriptionRequest;
use App\Services\Payments\Modular\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ModularPaymentApiController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * List all modular payment drivers and capabilities.
     */
    public function modules(): JsonResponse
    {
        return ApiResponse::success(
            [
                'default_gateway' => $this->gatewayManager->getDefaultDriver(),
                'modules' => $this->gatewayManager->catalog(),
            ],
            'Modular payment drivers retrieved successfully.'
        );
    }

    /**
     * Initiate a hosted or embedded checkout session using a specified modular driver.
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.50'],
            'currency' => ['required', 'string', 'size:3'],
            'success_url' => ['required', 'url'],
            'cancel_url' => ['required', 'url'],
            'customer_email' => ['nullable', 'email'],
            'description' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'string', 'max:100'],
        ]);

        $driverKey = $validated['driver'] ?? $this->gatewayManager->getDefaultDriver();

        try {
            $driver = $this->gatewayManager->directSdkDriver($driverKey);

            $sessionRequest = new CheckoutSessionRequest(
                amount: (float) $validated['amount'],
                currency: strtoupper($validated['currency']),
                successUrl: $validated['success_url'],
                cancelUrl: $validated['cancel_url'],
                customerEmail: $validated['customer_email'] ?? $request->user()?->email,
                description: $validated['description'] ?? null,
                referenceId: $validated['reference_id'] ?? null
            );

            $result = $driver->createCheckoutSession($sessionRequest);

            return ApiResponse::success([
                'driver' => $driverKey,
                'session_id' => $result->sessionId,
                'redirect_url' => $result->redirectUrl,
                'client_secret' => $result->clientSecret,
            ], 'Checkout session initialized successfully.', 201);
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'CHECKOUT_INIT_FAILED',
                400
            );
        }
    }

    /**
     * Generate customer self-service billing portal session (Stripe Cashier, Direct Stripe, PayPal, etc.).
     */
    public function createBillingPortalSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver' => ['nullable', 'string'],
            'return_url' => ['required', 'url'],
            'customer_id' => ['nullable', 'string'],
        ]);

        $driverKey = $validated['driver'] ?? 'cashier';

        try {
            $driver = $this->gatewayManager->subscriptionDriver($driverKey);
            // Always the caller: a portal for someone else's customer ID is
            // their cards and subscriptions. `customer_id` is not honoured.
            $customerId = (string) $request->user()->id;

            $portalRequest = new CustomerPortalRequest(
                customerId: $customerId,
                returnUrl: $validated['return_url']
            );

            $result = $driver->createBillingPortalSession($portalRequest);

            return ApiResponse::success([
                'driver' => $driverKey,
                'portal_url' => $result->portalUrl,
                'session_id' => $result->sessionId,
            ], 'Billing portal session generated.');
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'PORTAL_SESSION_FAILED',
                400
            );
        }
    }

    /**
     * Create recurring subscription with trial period and optional discount coupon.
     */
    public function createSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver' => ['nullable', 'string'],
            'plan_id' => ['required', 'string', 'max:100'],
            'cadence' => ['nullable', 'string', 'in:monthly,yearly,weekly'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'string'],
        ]);

        $driverKey = $validated['driver'] ?? 'cashier';

        try {
            $driver = $this->gatewayManager->subscriptionDriver($driverKey);

            $subRequest = new SubscriptionRequest(
                planId: $validated['plan_id'],
                // Always the caller; `customer_id` is not honoured.
                customerId: (string) $request->user()->id,
                cadence: $validated['cadence'] ?? 'monthly',
                trialDays: $validated['trial_days'] ?? null,
                couponCode: $validated['coupon_code'] ?? null
            );

            $result = $driver->createSubscription($subRequest);

            return ApiResponse::success([
                'driver' => $driverKey,
                'subscription_id' => $result->subscriptionId,
                'status' => $result->status,
                'plan_id' => $result->planId,
                'trial_ends_at' => $result->trialEndsAt,
                'next_billing_date' => $result->nextBillingDate,
                'checkout_url' => $result->checkoutUrl,
            ], 'Subscription initiated successfully.', 201);
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'SUBSCRIPTION_CREATION_FAILED',
                400
            );
        }
    }

    /**
     * Cancel an active recurring subscription.
     */
    public function cancelSubscription(Request $request, string $id): JsonResponse
    {
        // The drivers keep no record of whose subscription an ID is, so a
        // resident's cancellation cannot be checked against them. Billing
        // administrators only, until a driver can confirm ownership.
        $this->authorize('manageBilling');

        $driverKey = $request->query('driver', 'cashier');

        try {
            $driver = $this->gatewayManager->subscriptionDriver($driverKey);
            $immediately = $request->boolean('immediately', false);

            $success = $driver->cancelSubscription($id, $immediately);

            return ApiResponse::success([
                'driver' => $driverKey,
                'subscription_id' => $id,
                'cancelled' => $success,
            ], 'Subscription cancelled successfully.');
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'SUBSCRIPTION_CANCEL_FAILED',
                400
            );
        }
    }

    /**
     * Validate promotional coupon / discount code.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'min:2', 'max:50'],
            'driver' => ['nullable', 'string'],
        ]);

        $driverKey = $validated['driver'] ?? 'cashier';

        try {
            $driver = $this->gatewayManager->subscriptionDriver($driverKey);
            $result = $driver->validateCoupon($validated['coupon_code']);

            if (! $result->isValid) {
                return ApiResponse::error(
                    $result->errorMessage ?? 'Invalid coupon code.',
                    'INVALID_COUPON',
                    422
                );
            }

            return ApiResponse::success([
                'code' => $result->code,
                'discount_type' => $result->discountType,
                'discount_value' => $result->discountValue,
                'currency' => $result->currency,
                'description' => $result->description,
            ], 'Coupon code is valid.');
        } catch (Throwable $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'COUPON_VALIDATION_ERROR',
                400
            );
        }
    }
}
