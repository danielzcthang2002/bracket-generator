<?php

namespace App\Bracket\Providers;

use Illuminate\Support\ServiceProvider;

class DtBracketServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
