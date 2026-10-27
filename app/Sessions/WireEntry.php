<?php

namespace App\Sessions;

use App\NutsAndBolts\Json;
use Fabricate\NutsAndBolts\Messages\MCPNotification;
use Fabricate\NutsAndBolts\Messages\MCPRequest;
use Fabricate\NutsAndBolts\Messages\MCPResponse;
use Fabricate\NutsAndBolts\Messages\RoutedMCPMessage;

/** One JSON-RPC message on a session's wire, in either direction. */
final readonly class WireEntry
{
    public const string OUT = 'out';

    public const string IN = 'in';

    public function __construct(
        public string $direction,
        public string $kind,
        public string $method,
        public string $id,
        public string $json,
        public float $at,
    ) {}

    /** A line the client wrote to its connection. */
    public static function outgoing(string $line, ?float $at = null): self
    {
        $decoded = json_decode($line, true);
        $decoded = is_array($decoded) ? $decoded : [];
        $method = (string) ($decoded['method'] ?? '');
        $id = array_key_exists('id', $decoded) ? (string) $decoded['id'] : '';

        $kind = match (true) {
            isset($decoded['error']) => 'error',
            $method === '' => 'response',
            $id === '' => 'notification',
            default => 'request',
        };

        return new self(self::OUT, $kind, $method, $id, Json::pretty($decoded === [] ? $line : $decoded), $at ?? microtime(true));
    }

    /** A decoded message the connection handed to the client. */
    public static function incoming(object $message, ?float $at = null): self
    {
        $at ??= microtime(true);

        if ($message instanceof MCPRequest) {
            return new self(self::IN, 'request', $message->method, (string) $message->id, Json::pretty(json_decode($message->toJson(), true)), $at);
        }

        if ($message instanceof MCPNotification) {
            $payload = method_exists($message, 'toJson')
                ? json_decode($message->toJson(), true)
                : ['jsonrpc' => '2.0', 'method' => $message->method, 'params' => $message->params];

            return new self(self::IN, 'notification', $message->method, '', Json::pretty($payload), $at);
        }

        if ($message instanceof MCPResponse) {
            $kind = is_null($message->error) && ! isset(json_decode($message->toJson(), true)['error']) ? 'response' : 'error';

            return new self(self::IN, $kind, '', (string) ($message->id ?? ''), Json::pretty(json_decode($message->toJson(), true)), $at);
        }

        if ($message instanceof RoutedMCPMessage) {
            return new self(self::IN, 'message', $message->method, '', Json::pretty(['method' => $message->method, 'params' => $message->params]), $at);
        }

        return new self(self::IN, 'message', '', '', Json::pretty(['object' => $message::class]), $at);
    }

    public function isNotification(): bool
    {
        return $this->kind === 'notification';
    }

    /** What the History table shows for this entry's method column. */
    public function label(): string
    {
        return $this->method !== '' ? $this->method : ($this->kind === 'error' ? 'error' : 'response');
    }

    /** @return array<string, string> a History table row */
    public function row(int $number): array
    {
        return [
            'n' => (string) $number,
            'dir' => $this->direction === self::OUT ? '→' : '←',
            'kind' => $this->kind,
            'method' => $this->label(),
            'id' => $this->id,
            'at' => date('H:i:s', (int) $this->at).sprintf('.%03d', (int) (($this->at - floor($this->at)) * 1000)),
        ];
    }
}
