<?php

return [
    /*
     | Google AI (Gemini) — replaces the Genkit googleAI() plugin configured in
     | src/ai/genkit.ts. Used by App\Services\NotificationTargetingService.
     */
    'googleai' => [
        'key' => env('GOOGLE_AI_API_KEY'),
        'model' => env('GOOGLE_AI_MODEL', 'gemini-2.0-flash'),
        'endpoint' => env('GOOGLE_AI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Stripe
    |---------------------------------------------------------------------------
    |
    | This block did not exist, while StripePaymentService read
    | config('services.stripe.secret') to decide whether to talk to Stripe.
    | The key was therefore always null, the live branch was unreachable
    | whatever the operator put in .env, and every checkout silently took the
    | simulated path that marked invoices paid without charging anyone.
    |
    | Leave unset and the Stripe routes 404 rather than pretending to settle.
    |
    */
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
