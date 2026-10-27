<?php

namespace App\Screens;

use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Voyager\Contracts\Signals\NamedSignal;

/** One page of the deck: built on first mount, shown and hidden as the sidebar picks it. */
abstract class Screen
{
    protected ?TKColumn $root = null;

    abstract public function key(): string;

    abstract public function index(TKColumn $root): void;

    /** Take a piece of mail; true when this screen handled it. */
    abstract public function deliver(NamedSignal $mail): bool;

    public function mount(TKPrimitiveGroup $deck): void
    {
        if (is_null($this->root)) {
            $this->root = $deck->column($this->key(), 8, 12);
            $this->root->fill();
            $this->index($this->root);
        }
    }

    public function isMounted(): bool
    {
        return ! is_null($this->root);
    }

    public function show(): void
    {
        $this->root?->show();
    }

    public function hide(): void
    {
        $this->root?->hide();
    }

    /** Take the screen's primitives out of the window for good. */
    public function unmount(): void
    {
        $this->root?->remove();
        $this->root = null;
    }

    /** One frame. */
    public function tick(): void {}
}
