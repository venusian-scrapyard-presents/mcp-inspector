<?php

use App\Sessions\InspectedClient;
use App\Sessions\WireEntry;
use Fabricate\Client\ClientStatus;

it('proposes the version its session chose and leaves the supported list as it was', function () {
    [$client, $connection] = inspectedClient('2024-11-05');

    $client->open();

    expect($connection->sent[0]['method'])->toBe('initialize')
        ->and($connection->sent[0]['params']['protocolVersion'])->toBe('2024-11-05')
        ->and($client->proposedVersion())->toBe('2024-11-05')
        ->and(config(InspectedClient::VERSIONS_KEY))->toBe(['2025-03-26', '2024-11-05']);
});

it('proposes the newest version when the session chose it', function () {
    [$client, $connection] = inspectedClient('2025-03-26');

    $client->open();

    expect($connection->sent[0]['params']['protocolVersion'])->toBe('2025-03-26');
});

it('accepts a server that answers with another version the application supports', function () {
    [$client, $connection] = inspectedClient('2024-11-05');

    $client->open();
    $connection->handshake(version: '2025-03-26');

    expect($client->status())->toBe(ClientStatus::Connected)
        ->and($client->protocolVersion())->toBe('2025-03-26');
});

it('still refuses a version outside the supported list', function () {
    [$client, $connection] = inspectedClient('2024-11-05');

    $client->open();
    $connection->handshake(version: '2030-01-01');

    expect($client->status())->toBe(ClientStatus::Error)
        ->and($client->lastError())->toContain('2030-01-01');
});

it('records both directions of the wire in order', function () {
    [$client, $connection, $log] = inspectedClient();

    $client->open();
    $connection->handshake(['tools' => ['listChanged' => true]]);
    $connection->reply(['id' => $connection->last()['id'], 'result' => ['tools' => [['name' => 'echo', 'inputSchema' => ['type' => 'object']]]]]);

    $entries = $log->entries();

    expect(array_map(fn (WireEntry $entry): string => "{$entry->direction} {$entry->kind} {$entry->label()}", $entries))->toBe([
        'out request initialize',
        'in response response',
        'out notification notifications/initialized',
        'out request tools/list',
        'in response response',
    ])
        ->and($entries[0]->id)->toBe('0')
        ->and($entries[1]->id)->toBe('0')
        ->and(json_decode($entries[4]->json, true)['result']['tools'][0]['name'])->toBe('echo')
        ->and($log->revision())->toBe(5)
        ->and($client->ready()->wait())->toBe($client);
});

it('keeps the server\'s notifications apart from the rest of the wire', function () {
    [$client, $connection, $log] = inspectedClient();

    $client->open();
    $connection->handshake();
    $connection->reply(['method' => 'notifications/message', 'params' => ['level' => 'info', 'data' => 'hello from the server']]);

    $notifications = $log->notifications();

    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]->method)->toBe('notifications/message')
        ->and($notifications[0]->direction)->toBe(WireEntry::IN)
        ->and(json_decode($notifications[0]->json, true)['params']['data'])->toBe('hello from the server')
        ->and(count($log->entries()))->toBe(4);
});

it('records a JSON-RPC error reply as an error entry', function () {
    [$client, $connection, $log] = inspectedClient();

    $client->open();
    $connection->reply(['id' => 0, 'error' => ['code' => -32603, 'message' => 'boom']]);

    $last = $log->entries()[array_key_last($log->entries())];

    expect($last->direction)->toBe(WireEntry::IN)
        ->and($last->kind)->toBe('error')
        ->and($last->label())->toBe('error')
        ->and($client->status())->toBe(ClientStatus::Error);
});
