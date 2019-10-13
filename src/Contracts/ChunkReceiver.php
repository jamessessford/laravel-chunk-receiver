<?php

namespace JamesSessford\LaravelChunkReceiver\Contracts;

use Closure;

interface ChunkReceiver
{
    /**
     * Chunked upload handler.
     *
     * @param  string $name
     * @param  Closure $closure
     * @return Closure|string[]
     */
    public function receive(string $name, Closure $closure);
}
