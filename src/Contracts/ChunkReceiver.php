<?php

namespace JamesSessford\LaravelChunkReceiver\Contracts;

use Closure;

interface ChunkReceiver
{
    /**
     * Handle an incoming request containing a chunked file.
     */
    public function receive(string $name, Closure $closure);
}
