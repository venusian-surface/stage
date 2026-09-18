<?php

namespace Surface\Stage;

use Voyager\Contracts\Vessel\Vessel;
use Voyager\NutsAndBolts\ServiceProvider;

class StageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 3).'/config/stage.php',
            'stage',
        );

        $this->app->singleton(StageManager::class, fn (Vessel $app) => new StageManager($app));

        $this->app->singleton('stages', fn ($app) => $app->make(StageManager::class));
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 3).'/config/stage.php' => $this->app->configPath('stage.php'),
        ], 'surface-config');
    }
}
