<?php

namespace App\Providers;

use Voyager\NutsAndBolts\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        ini_set('memory_limit', (string) config('app.memory_limit', '256M'));
    }

    public function boot(): void {}
}
