<?php

use Voyager\NutsAndBolts\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name and ID
    |--------------------------------------------------------------------------
    |
    | The name shows in the window title and the About box; the id names the
    | app to the platform (bundle id on macOS, application id on Linux).
    |
    */

    'name' => env('APP_NAME', 'MCP Inspector'),

    'id' => env('APP_ID', 'com.venusian.mcp-inspector'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        //
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | MCP Inspector Application Configuration
    |--------------------------------------------------------------------------
    |
    | The toolkit the main window opens with, the memory ceiling for a run,
    | and the size the window opens at.
    |
    */

    'toolkit_bridge' => env('TOOLKIT_BRIDGE', 'gtk'),

    'memory_limit' => env('APP_MEMORY_LIMIT', '256M'),

    'starting_resolution' => [
        'width' => 1180,
        'height' => 760,
    ],

];
