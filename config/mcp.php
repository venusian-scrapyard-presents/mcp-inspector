<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | Where #[Server] classes are discovered, and the protocol versions this
    | application speaks. The inspector offers every listed version in a
    | session's version picker, newest first.
    |
    */

    'servers' => [
        'default_discovery_namespace' => "App\\Mcp\\Servers",
        'supported_mcp_versions' => ['2025-03-26', '2024-11-05'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Presets
    |--------------------------------------------------------------------------
    |
    | Entries in the shape Claude Desktop uses. Each one shows in the new
    | session form's preset picker and fills the form when chosen. The
    | inspector never connects to them on its own.
    |
    */

    'mcp_servers' => [
        'self' => [
            'type' => 'stdio',
            'command' => PHP_BINARY,
            'args' => ['rocket', 'mcp:cli', 'self'],
            'cwd' => base_path(),
        ],
        'pss' => [
            'type' => 'http',
            'url' => env('PSS_MCP_URL', 'https://projectsaturnstudios.com/mcp/operator'),
            'headers' => ['Authorization' => 'Bearer '.env('PSS_MCP_TOKEN', '')],
        ],
    ],

    'client' => [
        'connect_timeout' => 20,
        'stream_reconnects' => 5,
    ],

    'pagination' => [
        'page_size' => 50,
    ],

    'logging' => [
        'default_level' => 'info',
    ],

    'progress' => [
        'min_interval' => 0.1,
    ],

    'completion' => [
        'rate_limit' => [
            'max' => 10,
            'per_seconds' => 1,
        ],
    ],

];
