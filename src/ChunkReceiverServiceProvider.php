<?php

namespace JamesSessford\LaravelChunkReceiver;

use Illuminate\Support\ServiceProvider;
use JamesSessford\LaravelChunkReceiver\Contracts\ChunkReceiver as ChunkReceiverContract;
use JamesSessford\LaravelChunkReceiver\Facades\ChunkReceiver as ChunkReceiverFacade;

final class ChunkReceiverServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/chunk-receiver.php' => config_path('chunk-receiver.php'),
            ], 'config');
        }
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/chunk-receiver.php',
            'chunk-receiver'
        );

        $this->app->singleton(ChunkReceiverContract::class, function ($app) {
            return new ChunkReceiver($app);
        });

        $this->app->alias(ChunkReceiverFacade::class, 'ChunkReceiver');
    }
}
