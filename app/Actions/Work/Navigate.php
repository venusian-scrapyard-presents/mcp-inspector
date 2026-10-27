<?php

namespace App\Actions\Work;

use App\GlobalVariables;

/** Show one deck page: the new session form, or an open session's screen. */
final class Navigate
{
    public static function to(string $key, GlobalVariables $bag): void
    {
        if ($key === $bag->current) {
            return;
        }

        $bag->currentScreen()?->hide();
        $bag->current = $key;

        $screen = $bag->currentScreen();

        if (is_null($screen)) {
            $bag->current = null;

            return;
        }

        $screen->mount($bag->deck);
        $screen->show();

        // The sidebar row follows the shown session; the form shows no row. A session opened this
        // frame has no row yet: DrawSidebar selects it when it adds the row.
        $row = array_search($key, $bag->sessions->names(), true);
        $table = $bag->session_table;

        if (! is_null($table) && ($row === false || $row < count($table->rows()))) {
            $table->selectRow($row === false ? null : $row);
        }
    }
}
