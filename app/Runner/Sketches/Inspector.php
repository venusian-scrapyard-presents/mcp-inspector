<?php

namespace App\Runner\Sketches;

use App\Actions\Bootstrap\BootstrapToolkitEngine;
use App\Actions\Bootstrap\BootstrapToolkitWindow;
use App\Actions\Work\DeliverMail;
use App\Actions\Work\DrawSidebar;
use App\Actions\Work\Navigate;
use App\GlobalVariables;
use Surface\Core\MagicAliases\Toolkit;
use Voyager\Contracts\Sketches\SketchLoopResult;
use Voyager\IOPools\IOPools;
use Voyager\Sketches\Sketch;

class Inspector extends Sketch
{
    protected string $description = 'Run the MCP Inspector';

    private GlobalVariables $bag;

    public function boot(): void
    {
        $this->bag = GlobalVariables::make();
        $this->bag->event_loop = IOPools::eventLoop();
        $this->bag->session = BootstrapToolkitEngine::run($this->bag->event_loop);
        $this->bag->info = fn (string $output) => $this->info($output);
        $this->bag->warn = fn (string $output) => $this->warn($output);
    }

    /**
     * One frame. The runner calls it at the refresh rate.
     *
     * @param list<object> $mail what the loop delivered since the last frame; empty when nothing did
     */
    public function loop(array $mail = []): SketchLoopResult
    {
        if (! $this->bag->started) {
            $this->start();
        } elseif ($mail !== []) {
            new DeliverMail($this->bag)->run($mail);
        }

        if ($this->bag->ending || ! ($this->bag->window?->isOpen() ?? false)) {
            return SketchLoopResult::STOP;
        }

        DrawSidebar::run($this->bag);

        foreach ($this->bag->screens() as $screen) {
            if ($screen->isMounted()) {
                $screen->tick();
            }
        }

        return SketchLoopResult::CONTINUE;
    }

    public function shutdown(): void
    {
        $this->warn('Closing sessions');
        $this->bag->sessions->closeAll();
        Toolkit::window()->closeAll();
        $this->bag->session?->leaveLoop();
        $this->bag->session?->disconnect();
        $this->info('Inspector closed.');
    }

    private function start(): void
    {
        $this->bag->started = true;
        BootstrapToolkitWindow::run($this->bag)->present();
        Navigate::to(GlobalVariables::FORM, $this->bag);
        $this->info('Inspector started. New session opens a connection; each open session keeps running until closed.');
    }
}
