<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Public payment links
    |---------------------------------------------------------------------------
    |
    | Off by default, deliberately.
    |
    | `UniversalPaymentLinkController` serves an unauthenticated page that
    | anyone holding the link can submit. Its payments are now recorded as
    | `pending` and count only once an administrator confirms them from the
    | billing ledger, so a submission can no longer settle anything by itself.
    |
    | It stays off because the page is not ready: its form still posts a card
    | channel it has no checkout for, so it needs a "report a payment" form
    | offering the office-confirmed channels before it is switched on.
    |
    */

    'public_links_enabled' => (bool) env('PAYMENT_PUBLIC_LINKS_ENABLED', false),

    /*
    |---------------------------------------------------------------------------
    | Card payments: which processor takes them
    |---------------------------------------------------------------------------
    |
    | `stripe` or `wipay`. Card payment is offered only when a processor is
    | configured: left empty, Stripe is used if its keys are set (for estates
    | where Stripe supports the merchant), and otherwise card is unavailable.
    | Nothing is hard-wired to a processor; see
    | App\Services\Payments\Providers\ProviderRegistry.
    |
    */

    'card_provider' => env('PAYMENTS_CARD_PROVIDER'),

    'providers' => [

        /*
         | WiPay hosted payment page (Payments API 1.0.8). Sandbox: account
         | number 1234567890, API key 123. The API key signs the result WiPay
         | sends back; it never leaves the server.
         */
        'wipay' => [
            'account_number' => env('WIPAY_ACCOUNT_NUMBER'),
            'api_key' => env('WIPAY_API_KEY'),
            'environment' => env('WIPAY_ENVIRONMENT', 'sandbox'),   // sandbox | live
            'country_code' => env('WIPAY_COUNTRY_CODE', 'JM'),      // BB | JM | TT
            'fee_structure' => env('WIPAY_FEE_STRUCTURE', 'merchant_absorb'), // customer_pay | merchant_absorb | split
            'origin' => env('WIPAY_ORIGIN', 'CommunityHub'),
        ],

    ],

];
