<?php

namespace App\Sessions;

use Fabricate\Contracts\Client\Connection;

/** The transport's own connection, with every line the client writes copied into the session's wire log. */
final class RecordingConnection implements Connection
{
    public function __construct(
        private readonly Connection $inner,
        private readonly WireLog $log,
    ) {}

    public function open(): void
    {
        $this->inner->open();
    }

    public function send(string $line): void
    {
        $this->log->out($line);
        $this->inner->send($line);
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function closed(): bool
    {
        return $this->inner->closed();
    }

    public function describe(): string
    {
        return $this->inner->describe();
    }
}
