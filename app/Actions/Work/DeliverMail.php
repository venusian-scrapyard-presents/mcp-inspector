<?php

namespace App\Actions\Work;

use App\GlobalVariables;
use App\Screens\SessionScreen;
use Surface\Contracts\Windows\Mail\MenuActivated;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\RowSelected;
use Surface\Contracts\Windows\Mail\WindowResized;
use Voyager\Contracts\Signals\NamedSignal;

/** Route one frame's mail: the app's own items first, then the shown screen; the rest goes back out as a signal. */
final class DeliverMail
{
    public function __construct(private readonly GlobalVariables $bag) {}

    /** @param list<object> $incoming */
    public function run(array $incoming): void
    {
        foreach ($incoming as $mail) {
            if (! $mail instanceof NamedSignal) {
                continue;
            }

            if ($this->quitRequested($mail)) {
                $this->bag->ending = true;

                return;
            }

            if ($mail instanceof WindowResized) {
                signal($mail);
            } elseif ($mail instanceof ButtonClicked && $mail->uuid() === $this->bag->new_button?->uuid()) {
                Navigate::to(GlobalVariables::FORM, $this->bag);
            } elseif ($mail instanceof RowSelected && $mail->uuid() === $this->bag->session_table?->uuid()) {
                $this->sessionPicked($mail->row);
            } elseif ($mail instanceof MenuActivated) {
                $this->menu($mail->item);
            } elseif (! ($this->bag->currentScreen()?->deliver($mail) ?? false)) {
                signal($mail);
            }
        }
    }

    private function sessionPicked(?int $row): void
    {
        $name = $this->bag->sessions->names()[$row ?? -1] ?? null;

        if (! is_null($name)) {
            Navigate::to($name, $this->bag);
        }
    }

    private function menu(string $item): void
    {
        if ($item === 'new-session') {
            Navigate::to(GlobalVariables::FORM, $this->bag);

            return;
        }

        $screen = $this->bag->currentScreen();

        if ($screen instanceof SessionScreen) {
            $screen->menu($item);
        }
    }

    private function quitRequested(NamedSignal $mail): bool
    {
        return in_array($mail->name(), ['quit.requested', 'window.closed.inspector'], true);
    }
}
