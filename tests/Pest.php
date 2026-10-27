<?php

use App\Sessions\InspectedClient;
use App\Sessions\RecordingConnection;
use App\Sessions\WireLog;
use Tests\Fixtures\FakeConnection;
use Tests\Fixtures\FakeLog;
use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\Loop;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\Vessel\ControlPanel;

/*
|--------------------------------------------------------------------------
| Inspector helpers
|--------------------------------------------------------------------------
|
| The session classes read config and the log from the container and ride a
| loop. These build a container holding only that, with a connection that
| records what the client sends and takes replies by hand. No server runs
| and no application boots.
|
*/

function testLoop(int $pace_ms = 5): EventLoop
{
    $registry = new ResourceRegistry();

    return new EventLoop($registry, new LoopWaiter($registry, new StreamSelectWaiterBackend(), $pace_ms * 1_000_000), new GuzzlePromiseEngine());
}

function bareApp(array $config = [], ?EventLoop $loop = null): ControlPanel
{
    $app = new ControlPanel();
    ControlPanel::setInstance($app);
    $app->registerInstance('config', new Repository(['mcp' => array_replace_recursive(require dirname(__DIR__).'/vendor/superconductor/mcp/config/mcp.php', $config)]));
    $app->registerInstance('log', new FakeLog());
    $app->registerInstance(Loop::class, $loop ?? testLoop());

    return $app;
}

/**
 * A session's client on a recording connection with no server behind it.
 *
 * @return array{0: InspectedClient, 1: FakeConnection, 2: WireLog}
 */
function inspectedClient(string $version = '2024-11-05', array $config = []): array
{
    $app = bareApp($config);
    $log = new WireLog();
    $client = new InspectedClient('bench', $app->make(Loop::class), $version, $log);
    $connection = new FakeConnection($client);
    $client->attach(new RecordingConnection($connection, $log));

    return [$client, $connection, $log];
}
