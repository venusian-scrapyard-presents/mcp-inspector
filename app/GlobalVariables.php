<?php

namespace App;

use App\Screens\NewSessionScreen;
use App\Screens\Screen;
use App\Screens\SessionScreen;
use App\Sessions\SessionStore;
use Closure;
use Surface\Contracts\Bridge\BridgedToolkitSession;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKSeparator;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\ToolkitWindow;
use Voyager\Contracts\IOPools\Loop;

/** Everything the sketch, its actions and its screens share for one run. */
final class GlobalVariables
{
    /** The deck key of the new session form. */
    public const string FORM = 'form';

    private static ?self $instance = null;

    public ?Closure $info = null;

    public ?Closure $warn = null;

    public ?ToolkitWindow $window = null;

    public bool $started = false;

    public bool $ending = false;

    public ?TKColumn $deck = null;

    public ?TKColumn $sidebar = null;

    public ?TKSeparator $divider = null;

    public ?TKButton $new_button = null;

    public ?TKTable $session_table = null;

    public ?TKLabel $status_label = null;

    public ?Loop $event_loop = null;

    public ?BridgedToolkitSession $session = null;

    public SessionStore $sessions;

    public ?NewSessionScreen $form = null;

    /** @var array<string, SessionScreen> by session name */
    public array $screens = [];

    /** self::FORM, or the name of the session whose screen shows */
    public ?string $current = null;

    /** What the sidebar table showed last, to redraw it only on change. */
    public string $sidebar_signature = '';

    private function __construct()
    {
        $this->sessions = new SessionStore();
    }

    public static function make(): self
    {
        return self::$instance ??= new self();
    }

    public function currentScreen(): ?Screen
    {
        return match (true) {
            $this->current === self::FORM => $this->form,
            is_string($this->current) => $this->screens[$this->current] ?? null,
            default => null,
        };
    }

    /** @return list<Screen> */
    public function screens(): array
    {
        return [...array_filter([$this->form]), ...array_values($this->screens)];
    }

    public function info(string $message): void
    {
        ($this->info)($message);
    }

    public function warn(string $message): void
    {
        ($this->warn)($message);
    }
}
