<?php

namespace App\Sessions;

use Fabricate\Client\MCPClient;
use Voyager\Contracts\IOPools\Loop;

/**
 * An MCPClient that proposes the protocol version its session chose and copies what it receives
 * into the session's wire log.
 *
 * MCPClient reads the version it proposes from mcp.servers.supported_mcp_versions while open()
 * runs, and checks the server's answer against the same list when it arrives. The list is set to
 * the session's version for the length of open() and restored after it, so each session proposes
 * its own version and every supported answer is still accepted.
 */
final class InspectedClient extends MCPClient
{
    public const string VERSIONS_KEY = 'mcp.servers.supported_mcp_versions';

    public function __construct(
        string $name,
        Loop $event_loop,
        private readonly string $version,
        private readonly WireLog $log,
    ) {
        parent::__construct($name, $event_loop);
    }

    public function proposedVersion(): string
    {
        return $this->version;
    }

    public function log(): WireLog
    {
        return $this->log;
    }

    public function open(): void
    {
        $supported = config(self::VERSIONS_KEY, ['2024-11-05']);
        config([self::VERSIONS_KEY => [$this->version]]);

        try {
            parent::open();
        } finally {
            config([self::VERSIONS_KEY => $supported]);
        }
    }

    public function receive(array $messages): void
    {
        foreach ($messages as $message) {
            if (is_object($message)) {
                $this->log->in($message);
            }
        }

        parent::receive($messages);
    }
}
