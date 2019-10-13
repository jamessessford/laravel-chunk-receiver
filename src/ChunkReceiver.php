<?php

namespace JamesSessford\LaravelChunkReceiver;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use JamesSessford\LaravelChunkReceiver\Contracts\ChunkReceiver as Contract;

final class ChunkReceiver implements Contract
{
    /**
     * @var Illuminate\Contracts\Foundation\Application
     */
    private $app;

    /**
     * Class constructor.
     *
     * @param  \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Chunked upload handler.
     *
     * @param  string $name
     * @param  Closure $closure
     * @return Closure|string[]
     */
    public function receive(string $name, Closure $closure)
    {
        $receivedFileHandler = $this->app->make(ReceivedFile::class);

        return $receivedFileHandler->processUpload($name, $closure);
    }
}
