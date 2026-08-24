<?php

declare(strict_types=1);

use App\Logging\StoryFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | docs/alignment.md §2: every log line is one JSON object on stdout, in
    | every environment. There is one channel that writes lines and one
    | format they are written in, so nothing decides between prose and JSON
    | by environment.
    |
    | The test runner points LOG_CHANNEL at "null": a suite that printed the
    | application's log to the same stream as its own output would be
    | unreadable. The tests that read log lines capture them through the
    | formatter below, so the payload under test is the deployed one.
    |
    */

    'default' => env('LOG_CHANNEL', 'stdout'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    */

    'channels' => [

        'stdout' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stdout',
            ],
            'formatter' => StoryFormatter::class,
            'formatter_with' => [
                // A failure shows the stack it came from while someone is
                // developing against it, and nowhere else.
                'tracesStacks' => (bool) env('APP_DEBUG', false),
            ],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        // Where the framework writes when the logger itself cannot be built,
        // which is the one moment there is no channel to write through.
        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
