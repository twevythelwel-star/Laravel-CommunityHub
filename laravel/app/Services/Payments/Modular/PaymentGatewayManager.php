<?php

declare(strict_types=1);

namespace App\Services\Payments\Modular;

use App\Services\Payments\Modular\Contracts\DirectSdkGatewayContract;
use App\Services\Payments\Modular\Contracts\PaymentModuleContract;
use App\Services\Payments\Modular\Contracts\SubscriptionGatewayContract;
use App\Services\Payments\Modular\Drivers\AdyenModuleDriver;
use App\Services\Payments\Modular\Drivers\AuthorizeNetModuleDriver;
use App\Services\Payments\Modular\Drivers\BraintreeModuleDriver;
use App\Services\Payments\Modular\Drivers\CashierModuleDriver;
use App\Services\Payments\Modular\Drivers\FlutterwaveModuleDriver;
use App\Services\Payments\Modular\Drivers\MollieModuleDriver;
use App\Services\Payments\Modular\Drivers\PayPalModuleDriver;
use App\Services\Payments\Modular\Drivers\PaystackModuleDriver;
use App\Services\Payments\Modular\Drivers\SquareModuleDriver;
use App\Services\Payments\Modular\Drivers\StripeSdkModuleDriver;
use InvalidArgumentException;

/**
 * Universal Payment & Subscription Gateway Manager.
 *
 * Coordinates optional modular payment integrations without hard-coupling
 * core application logic to any specific third-party proprietary SDK.
 */
class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentModuleContract>
     */
    protected array $drivers = [];

    public function __construct(
        CashierModuleDriver $cashier,
        StripeSdkModuleDriver $stripe,
        PayPalModuleDriver $paypal,
        SquareModuleDriver $square,
        AdyenModuleDriver $adyen,
        BraintreeModuleDriver $braintree,
        FlutterwaveModuleDriver $flutterwave,
        PaystackModuleDriver $paystack,
        MollieModuleDriver $mollie,
        AuthorizeNetModuleDriver $authorizenet
    ) {
        $this->registerDriver($cashier);
        $this->registerDriver($stripe);
        $this->registerDriver($paypal);
        $this->registerDriver($square);
        $this->registerDriver($adyen);
        $this->registerDriver($braintree);
        $this->registerDriver($flutterwave);
        $this->registerDriver($paystack);
        $this->registerDriver($mollie);
        $this->registerDriver($authorizenet);
    }

    public function registerDriver(PaymentModuleContract $driver): void
    {
        $this->drivers[$driver->key()] = $driver;
    }

    public function driver(?string $name = null): PaymentModuleContract
    {
        $name = $name ?: $this->getDefaultDriver();

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Payment module driver [{$name}] is not registered.");
        }

        return $this->drivers[$name];
    }

    public function subscriptionDriver(?string $name = null): SubscriptionGatewayContract
    {
        $driver = $this->driver($name);

        if (! $driver instanceof SubscriptionGatewayContract) {
            throw new InvalidArgumentException("Payment module driver [{$driver->key()}] does not support recurring subscriptions.");
        }

        return $driver;
    }

    public function directSdkDriver(?string $name = null): DirectSdkGatewayContract
    {
        $driver = $this->driver($name);

        if (! $driver instanceof DirectSdkGatewayContract) {
            throw new InvalidArgumentException("Payment module driver [{$driver->key()}] does not support direct SDK checkouts.");
        }

        return $driver;
    }

    public function hasDriver(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    public function getDefaultDriver(): string
    {
        return (string) config('payments.default_gateway', 'stripe');
    }

    /**
     * @return array<string, PaymentModuleContract>
     */
    public function getAllDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * @return array<string, PaymentModuleContract>
     */
    public function getAvailableDrivers(): array
    {
        return array_filter($this->drivers, fn (PaymentModuleContract $d) => $d->isAvailable());
    }

    /**
     * @return array<string, SubscriptionGatewayContract>
     */
    public function getSubscriptionDrivers(): array
    {
        /** @var array<string, SubscriptionGatewayContract> */
        return array_filter($this->drivers, fn ($d) => $d instanceof SubscriptionGatewayContract);
    }

    /**
     * Check if the active or specified driver supports a given capability.
     */
    public function supports(string $capability, ?string $driverName = null): bool
    {
        $name = $driverName ?: $this->getDefaultDriver();

        return isset($this->drivers[$name]) && $this->drivers[$name]->supports($capability);
    }

    /**
     * Generate metadata catalog for all modular drivers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach ($this->drivers as $key => $driver) {
            $catalog[] = [
                'key' => $key,
                'label' => $driver->label(),
                'is_configured' => $driver->isConfigured(),
                'is_available' => $driver->isAvailable(),
                'capabilities' => $driver->getCapabilities(),
                'supports_subscriptions' => $driver instanceof SubscriptionGatewayContract,
                'supports_direct_sdk' => $driver instanceof DirectSdkGatewayContract,
                'is_default' => $key === $this->getDefaultDriver(),
            ];
        }

        return $catalog;
    }
}
