<?php

namespace JamesSessford\LaravelChunkReceiver\Tests;

use File;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTempFiles();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \JamesSessford\LaravelChunkReceiver\ChunkReceiverServiceProvider::class,
        ];
    }

    /**
     * Load package alias.
     * @param  \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'ChunkReceiver' => \JamesSessford\LaravelChunkReceiver\Facades\ChunkReceiver::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('chunk-receiver.chunk_path', $this->getChunkDirectory());

        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => $this->getSupportDirectory(),
        ]);

        $app->bind('path.public', function () {
            return $this->getSupportDirectory();
        });
    }

    protected function setUpTempFiles()
    {
        $this->initializeDirectory($this->getTempDirectory());
        $this->initializeDirectory($this->getFilesDirectory());
    }

    protected function setExpectedException(string $exception)
    {
        parent::setExpectedException($exception);
    }

    protected function initializeDirectory($directory)
    {
        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }
        File::makeDirectory($directory);
    }

    public function getSupportDirectory($suffix = '')
    {
        return __DIR__.'/Support'.($suffix == '' ? '' : '/'.$suffix);
    }

    public function getTempDirectory($suffix = '')
    {
        return $this->getSupportDirectory().'/temp'.($suffix == '' ? '' : '/'.$suffix);
    }

    public function getFilesDirectory($suffix = '')
    {
        return $this->getTempDirectory().'/files'.($suffix == '' ? '' : '/'.$suffix);
    }

    public function getChunkDirectory($suffix = '')
    {
        return $this->getTempDirectory().'/chunks'.($suffix == '' ? '' : '/'.$suffix);
    }
}
