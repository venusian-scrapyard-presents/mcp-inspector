<?php

// The edit roles run on the focused field with the platform's own keys (⌘V / Ctrl+V).
$edit = ['label' => 'Edit', 'items' => [
    ['role' => 'undo', 'label' => 'Undo'],
    ['role' => 'redo', 'label' => 'Redo'],
    ['separator' => true],
    ['role' => 'cut', 'label' => 'Cut'],
    ['role' => 'copy', 'label' => 'Copy'],
    ['role' => 'paste', 'label' => 'Paste'],
    ['role' => 'select-all', 'label' => 'Select All'],
]];

// Session items are delivered to the sketch as MenuActivated mail by id.
$session = ['label' => 'Session', 'items' => [
    ['id' => 'new-session', 'label' => 'New Session', 'keys' => 'Primary+N'],
    ['id' => 'ping', 'label' => 'Ping', 'keys' => 'Primary+P'],
    ['id' => 'reconnect', 'label' => 'Reconnect', 'keys' => 'Primary+R'],
    ['separator' => true],
    ['id' => 'close-session', 'label' => 'Close Session'],
]];

return [
    'about' => [
        'name' => env('APP_NAME', 'MCP Inspector'),
        'version' => env('APP_VERSION'),
        'copyright' => '© '.date('Y').' Angel Gonzalez',
    ],

    // Shown on macOS when none of our windows has focus. Null = empty bar.
    'default_menu' => 'main',

    'menus' => [
        'main' => [
            ['label' => 'App', 'items' => [
                ['role' => 'about', 'label' => 'About'],
                ['separator' => true],
                ['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q'],
            ]],
            $edit,
            $session,
        ],
    ],
];
