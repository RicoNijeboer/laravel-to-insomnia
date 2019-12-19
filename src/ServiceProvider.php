<?php


namespace Rico\Insomnia;


use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Rico\Insomnia\Commands\RoutesToInsomnia;
use Rico\Insomnia\Commands\Routes;

class ServiceProvider extends BaseServiceProvider
{

    /**
     * @return void
     */
    public function register(): void
    {
        $this->commands([
                            RoutesToInsomnia::class,
                            Routes::class,
                        ]);
    }
}