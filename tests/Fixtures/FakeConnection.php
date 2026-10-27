<?php

namespace Tests\Fixtures;

use Fabricate\Client\MCPClient;
use Fabricate\Contracts\Client\Connection;
use Fabricate\NutsAndBolts\LineDecoder;
use RuntimeException;

/** The client's connection with no server behind it: records what goes out, delivers replies on demand. */
final class FakeConnection implements Connection
{
    /** @var list<array> each line the client sent, decoded */
    public array $sent = [];

    public bool $opened = false;

    public bool $closed = false;

    public bool $fail_open = false;

    public function __construct(private readonly MCPClient $client) {}

    public function open(): void
    {
        if ($this->fail_open) {
            throw new RuntimeException('could not start: fake');
        }

        $this->opened = true;
    }

    public function send(string $line): void
    {
        $this->sent[] = json_decode($line, true);
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function closed(): bool
    {
        return $this->closed;
    }

    public function describe(): string
    {
        return 'fake';
    }

    /** What the server would have written, handed to the client as one decoded line. */
    public function reply(array $message): void
    {
        $this->client->receive([LineDecoder::decodeString(json_encode(['jsonrpc' => '2.0', ...$message]))]);
    }

    public function last(): array
    {
        return $this->sent[array_key_last($this->sent)];
    }

    /** @return list<string> */
    public function methods(): array
    {
        return array_values(array_filter(array_column($this->sent, 'method')));
    }

    /** The initialize answer a well-behaved server gives, with the capabilities it advertises. */
    public function handshake(array $capabilities = [], string $version = '2024-11-05'): void
    {
        $this->reply(['id' => $this->sent[0]['id'], 'result' => ['protocolVersion' => $version, 'capabilities' => $capabilities, 'serverInfo' => ['name' => 'bench', 'version' => '1.0']]]);
    }
}
