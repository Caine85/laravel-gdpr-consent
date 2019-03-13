<?php namespace Foothing\Laravel\Consent;

use Foothing\Laravel\Consent\Commands\ConsentSetup;
use Illuminate\Support\ServiceProvider;

class ConsentServiceProvider extends ServiceProvider {

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . "/../migrations");

        $this->publishes([
            __DIR__ . '/../config/gdpr-consent.php' => config_path('gdpr-consent.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../migrations/' => database_path('migrations')
        ], 'migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConsentSetup::class,
            ]);
        }
    }

    public function register()
    {

    }
}
