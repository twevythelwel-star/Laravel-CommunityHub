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
];
