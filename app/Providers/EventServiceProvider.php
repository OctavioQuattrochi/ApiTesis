<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Aquí van los eventos y listeners, si los tenés
    ];

    public function boot()
    {
        //
    }
}
