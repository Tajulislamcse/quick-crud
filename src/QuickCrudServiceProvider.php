<?php

namespace QuickCrud;

use Illuminate\Support\ServiceProvider;
use QuickCrud\Commands\GenerateCrudCommand;

class QuickCrudServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateCrudCommand::class,
            ]);
        }
    }

    public function register()
    {
        //
    }
}
