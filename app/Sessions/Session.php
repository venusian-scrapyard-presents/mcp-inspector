<?php

namespace App\Sessions;

use Fabricate\Client\ClientStatus;
use SuperconductorMCP\SSE\Client\HttpConnection;
use SuperconductorMCP\SSE\Client\SseConnection;
use SuperconductorMCP\STDIO\Client\StdioConnection;
use Throwable;
use Voyager\Contracts\IOPools\Loop;

/** One connection to one server: its definition, its client, and the wire log they share. */
final class Session
{
    private function __construct(
        public readonly SessionDefinition $definition,
        public readonly InspectedClient $client,
        public readonly WireLog $log,
        public readonly float $opened_at,
    ) {}

    /**
     * Build the client on the transport the definition names and open it; connecting runs on the
     * loop from here. A log handed in keeps a reconnect's history in one place.
     */
    public static function open(SessionDefinition $definition, Loop $loop, ?WireLog $log = null): self
    {
        $log ??= new WireLog();
        $client = new InspectedClient($definition->name, $loop, $definition->version, $log);
        $entry = ['name' => $definition->name, ...$definition->toEntry()];

        $connection = match ($definition->type) {
            'stdio' => new StdioConnection($entry, $loop, $client),
            'http' => new HttpConnection($entry, $loop, $client),
            'sse' => new SseConnection($entry, $loop, $client),
        };

        $client->attach(new RecordingConnection($connection, $log));
        // status() and lastError() carry the outcome; nothing waits on the promise itself
        $client->ready()->error(fn (Throwable $e): null => null);
        $client->open();

        return new self($definition, $client, $log, microtime(true));
    }

    public function name(): string
    {
        return $this->definition->name;
    }

    public function status(): ClientStatus
    {
        return $this->client->status();
    }

    /** The version the server agreed to, or the proposed one with a mark while nothing is agreed yet. */
    public function version(): string
    {
        return $this->client->protocolVersion() ?? $this->definition->version.' ?';
    }

    public function close(): void
    {
        $this->client->close();
    }

    /** A fresh client and connection on the same definition, writing into the same log. */
    public function reopen(Loop $loop): self
    {
        $this->close();

        return self::open($this->definition, $loop, $this->log);
    }
}
