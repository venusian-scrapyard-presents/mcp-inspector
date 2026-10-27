<?php

namespace App\Actions\Bootstrap;

use App\GlobalVariables;
use App\Screens\NewSessionScreen;
use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Core\MagicAliases\Toolkit;

/** The main window: a sidebar of sessions on the left, the shown page on the right, a status line below. */
final class BootstrapToolkitWindow
{
    public const string WINDOW = 'inspector';

    public static function run(GlobalVariables $bag): ToolkitWindow
    {
        $window = Toolkit::window()->open(
            self::WINDOW,
            (int) config('app.starting_resolution.width', 1180),
            (int) config('app.starting_resolution.height', 760),
            'main',
        );
        $window->setTitle((string) config('app.name', 'MCP Inspector'));
        $bag->window = $window;

        $shell = $window->column('shell');
        $body = $shell->row('body');
        $body->fill();

        $bag->sidebar = self::sidebar($bag, $body);
        $bag->divider = $body->separator('divider', horizontal: false);
        $bag->deck = $body->column('deck');
        $bag->deck->fill();

        $shell->separator('rule');
        self::footer($bag, $shell);

        $bag->form = new NewSessionScreen();

        return $window;
    }

    private static function sidebar(GlobalVariables $bag, TKRow $body): TKColumn
    {
        $nav = $body->column('nav', 8, 12);
        $nav->minSize(330, 0);
        $nav->fill(false, true);
        $nav->label('title', 'MCP Inspector')->setFont(new FontSpec(20, FontWeight::BOLD));
        $bag->new_button = $nav->button('new', 'New session');
        $bag->session_table = $nav->table('sessions', [
            new TableColumn('name', 'Session'),
            new TableColumn('status', 'Status'),
            new TableColumn('version', 'Version'),
        ]);
        // Only the deck takes the spare width; the table takes the sidebar's spare height.
        $bag->session_table->fill(false, true);

        return $nav;
    }

    private static function footer(GlobalVariables $bag, TKColumn $shell): void
    {
        $footer = $shell->row('status', 8, 6);
        $bag->status_label = $footer->label('message', 'Open a session to begin.');
        $bag->status_label->fill(true, false);
    }
}
