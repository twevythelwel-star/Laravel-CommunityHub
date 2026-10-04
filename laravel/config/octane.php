<?php

use Laravel\Octane\Contracts\OperationTerminated;
use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TaskTerminated;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Events\TickTerminated;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Events\WorkerStopping;
use Laravel\Octane\Listeners\CloseMonologHandlers;
use Laravel\Octane\Listeners\CollectGarbage;
use Laravel\Octane\Listeners\DisconnectFromDatabases;
use Laravel\Octane\Listeners\EnsureUploadedFilesAreValid;
use Laravel\Octane\Listeners\EnsureUploadedFilesCanBeMoved;
use Laravel\Octane\Listeners\FlushOnce;
use Laravel\Octane\Listeners\FlushTemporaryContainerInstances;
use Laravel\Octane\Listeners\FlushUploadedFiles;
use Laravel\Octane\Listeners\ReportException;
use Laravel\Octane\Listeners\StopWorkerIfNecessary;
use Laravel\Octane\Octane;

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    |
    | Supported application servers: "frankenphp", "swoole", "roadrunner"
    |
    | FrankenPHP: Modern Go/Caddy server, Early Hints (103), automatic SSL, worker mode.
    | Swoole: Coroutine-based C extension, persistent in-memory tables, high concurrency.
    | RoadRunner: High-performance Go application server and process supervisor.
    |
    */

    'server' => env('OCTANE_SERVER', 'frankenphp'),

    'https' => env('OCTANE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Octane Event Listeners & State Sanitation
    |--------------------------------------------------------------------------
    |
    | Prevents memory bloat, database connection exhaustion, and cross-request
    | state leakage across long-lived PHP worker threads.
    |
    */

    'listeners' => [
        WorkerStarting::class => [
            EnsureUploadedFilesAreValid::class,
            EnsureUploadedFilesCanBeMoved::class,
        ],

        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            ...Octane::prepareApplicationForNextRequest(),
        ],

        RequestHandled::class => [
            //
        ],

        RequestTerminated::class => [
            FlushUploadedFiles::class,
        ],

        TaskReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
        ],

        TaskTerminated::class => [
            //
        ],

        TickReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
        ],

        TickTerminated::class => [
            //
        ],

        OperationTerminated::class => [
            FlushOnce::class,
            FlushTemporaryContainerInstances::class,
            CollectGarbage::class,
            DisconnectFromDatabases::class,
            CloseMonologHandlers::class,
            StopWorkerIfNecessary::class,
        ],

        WorkerErrorOccurred::class => [
            ReportException::class,
            StopWorkerIfNecessary::class,
        ],

        WorkerStopping::class => [
            CloseMonologHandlers::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm / Pre-Boot Services
    |--------------------------------------------------------------------------
    |
    | Pre-instantiated singletons kept in memory to eliminate cold start latency.
    |
    */

    'warm' => [
        ...Octane::defaultServicesToWarm(),
    ],

    /*
    |--------------------------------------------------------------------------
    | State Leak Sanitizer (Flushed Services)
    |--------------------------------------------------------------------------
    |
    | Service container bindings reset between consecutive HTTP requests.
    |
    */

    'flush' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane In-Memory Tables
    |--------------------------------------------------------------------------
    |
    | Ultra-fast memory cache tables for sub-millisecond barcode & RFID lookups.
    |
    */

    'tables' => [
        'gate_passes:5000' => [
            'pass_code' => 'string:64',
            'status' => 'string:32',
            'visitor_name' => 'string:128',
            'cached_at' => 'int',
        ],
        'rfid_tags:5000' => [
            'tag_uid' => 'string:64',
            'vehicle_plate' => 'string:32',
            'resident_id' => 'int',
        ],
    ],

    'cache' => [
        'rows' => 5000,
        'bytes' => 10000,
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        '.env',
    ],

    'garbage' => 50,

    'max_execution_time' => 30,

    'state_file' => env('OCTANE_STATE_FILE', storage_path('logs/octane-server-state.json')),

    'roadrunner' => [
        'command' => env('OCTANE_ROADRUNNER_COMMAND', 'vendor/bin/roadrunner-worker'),
        'http_middleware' => env('OCTANE_ROADRUNNER_HTTP_MIDDLEWARE', 'static'),
    ],

    'frankenphp' => [
        'worker' => env('OCTANE_FRANKENPHP_WORKER', 'public/frankenphp-worker.php'),
    ],

];
