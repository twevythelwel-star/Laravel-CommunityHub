<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Read by Illuminate\Http\Middleware\TrustProxies. Behind a TLS-terminating
    | proxy the app sees plain HTTP, so without this it generates http:// URLs
    | and redirects, and secure cookies misbehave. Set TRUSTED_PROXIES to the
    | proxy's address (or a comma-separated list, or * when only the proxy can
    | reach the container). Unset, nothing is trusted, which is right for a
    | local run.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
