<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // The dashboard subscribes with its session at /broadcasting/auth; the
    // native shell and scanners use /api/broadcasting/auth (routes/api.php).
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth', 'active']])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
         | Refused and missing pages inside the dashboard render as an Inertia
         | page rather than Laravel's stock error view, so the sidebar stays on
         | screen and the user has somewhere to go.
         |
         | This matters more since the route gates were tightened: a page a role
         | may not open now stops at the middleware, and without this the user
         | is handed a bare error document with no navigation at all — a dead
         | end in an app whose entire shell is the navigation.
         |
         | Scoped to the dashboard. Public Blade pages keep their own error
         | views, and the JSON API keeps returning JSON.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            $wantsInAppError = $request->user()
                && $request->is('dashboard', 'dashboard/*')
                && ! $request->expectsJson()
                && in_array($status, [403, 404, 419, 429, 500, 503], true);

            if (! $wantsInAppError) {
                return $response;
            }

            /*
             | Pass the exception's message through only when it says something
             | the page cannot. Controllers here raise useful ones — "You can
             | only manage visitors you registered." — but the framework's own
             | defaults ("This action is unauthorized.", "Not Found") are
             | vaguer than the copy on the page, so they are dropped.
             */
            $generic = ['This action is unauthorized.', 'Unauthenticated.', 'Not Found', 'Forbidden', 'Server Error'];
            $message = $exception instanceof HttpExceptionInterface ? trim($exception->getMessage()) : '';

            return Inertia::render('Error', [
                'status' => $status,
                'message' => ($message !== '' && ! in_array($message, $generic, true)) ? $message : null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
