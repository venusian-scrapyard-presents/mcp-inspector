<?php

namespace App\Actions\Bootstrap;

use Surface\Contracts\Bridge\BridgedToolkitSession;
use Surface\Core\MagicAliases\Toolkit;
use Voyager\Contracts\IOPools\Loop;

/** Connect the configured toolkit and hand it the loop's sleep. */
final class BootstrapToolkitEngine
{
    public static function run(Loop $event_loop): BridgedToolkitSession
    {
        /** @var BridgedToolkitSession $session */
        $session = Toolkit::bridge()
            ->driver(config('app.toolkit_bridge'))
            ->connect();

        $session->joinLoop($event_loop);

        return $session;
    }
}
