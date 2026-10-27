<?php

use App\Sessions\WireEntry;
use Fabricate\NutsAndBolts\LineDecoder;

it('classifies what the client writes', function () {
    $request = WireEntry::outgoing('{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"echo"}}', 1_700_000_000.25);
    $notification = WireEntry::outgoing('{"jsonrpc":"2.0","method":"notifications/initialized"}');
    $reply = WireEntry::outgoing('{"jsonrpc":"2.0","id":"s1","result":{}}');

    expect([$request->kind, $request->method, $request->id])->toBe(['request', 'tools/call', '3'])
        ->and([$notification->kind, $notification->method, $notification->id])->toBe(['notification', 'notifications/initialized', ''])
        ->and([$reply->kind, $reply->label(), $reply->id])->toBe(['response', 'response', 's1'])
        ->and($request->row(1))->toMatchArray(['n' => '1', 'dir' => '→', 'kind' => 'request', 'method' => 'tools/call', 'id' => '3'])
        ->and($request->row(1)['at'])->toEndWith('.250');
});

it('classifies what the server sends once decoded', function () {
    $response = WireEntry::incoming(LineDecoder::decodeString('{"jsonrpc":"2.0","id":0,"result":{"protocolVersion":"2024-11-05","capabilities":{},"serverInfo":{"name":"x","version":"1"}}}'));
    $notification = WireEntry::incoming(LineDecoder::decodeString('{"jsonrpc":"2.0","method":"notifications/tools/list_changed"}'));
    $ping = WireEntry::incoming(LineDecoder::decodeString('{"jsonrpc":"2.0","id":9,"method":"ping"}'));

    expect([$response->direction, $response->kind, $response->id])->toBe([WireEntry::IN, 'response', '0'])
        ->and(json_decode($response->json, true)['result']['serverInfo']['name'])->toBe('x')
        ->and([$notification->kind, $notification->method, $notification->isNotification()])->toBe(['notification', 'notifications/tools/list_changed', true])
        ->and([$ping->kind, $ping->method, $ping->id, $ping->row(2)['dir']])->toBe(['request', 'ping', '9', '←']);
});
