<?php

namespace JamesSessford\LaravelChunkReceiver\Facades;

use Illuminate\Support\Facades\Facade;
use JamesSessford\LaravelChunkReceiver\Contracts\ChunkReceiver as ChunkReceiverContract;

final class ChunkReceiver extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ChunkReceiverContract::class;
    }
}
