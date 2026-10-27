<?php

namespace App\Actions\Work;

use App\GlobalVariables;
use App\Sessions\Session;

/** Keep the sidebar's session rows current, redrawing only when a name, status or version changed. */
final class DrawSidebar
{
    public static function run(GlobalVariables $bag): void
    {
        $rows = array_map(fn (Session $session): array => [
            'name' => $session->name(),
            'status' => ucfirst($session->status()->value),
            'version' => $session->version(),
        ], array_values($bag->sessions->all()));

        $signature = json_encode($rows);

        if ($signature === $bag->sidebar_signature) {
            return;
        }

        $bag->sidebar_signature = (string) $signature;
        $bag->session_table?->setRows($rows);

        // setRows clears the selection: put it back on the shown session
        $row = array_search($bag->current, $bag->sessions->names(), true);
        $bag->session_table?->selectRow($row === false ? null : $row);
    }
}
