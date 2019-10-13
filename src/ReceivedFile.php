<?php

namespace JamesSessford\LaravelChunkReceiver;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Filesystem\Filesystem;
use JamesSessford\LaravelChunkReceiver\Exceptions\Exception;
use JamesSessford\LaravelChunkReceiver\Requests\ChunkReceiverRequest as Request;

final class ReceivedFile
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Illuminate\Filesystem\Filesystem
     */
    private $storage;

    /**
     * @var int
     */
    private $maxAge = 600;

    /**
     * Create new class instance.
     *
     * @param  Request $request
     * @param  Filesystem $file
     */
    public function __construct(Request $request, Filesystem $file)
    {
        $this->request = $request;
        $this->storage = $file;
        $this->buildChunkPath();
    }

    /**
     * Set chuck upload path.
     *
     * @return void
     */
    private function buildChunkPath(): void
    {
        $path = config('chunk-receiver.chunk_path');

        if (! $this->storage->isDirectory($path)) {
            $this->storage->makeDirectory($path, 0777, true);
        }
    }

    /**
     * Get chuck upload path.
     *
     * @return string
     */
    public function getChunkPath(): string
    {
        return config('chunk-receiver.chunk_path');
    }

    /**
     * Process uploaded files.
     *
     * @param  string $name
     * @param  \Closure $closure
     * @return string[]
     */
    public function processUpload(string $name, Closure $closure): array
    {
        $response = [];
        $response['jsonrpc'] = '2.0';

        $result = ($this->withChunks() ? $this->chunks($name, $closure) : $result = $this->single($name, $closure));

        $response['result'] = $result;

        return $response;
    }

    /**
     * Handle whole file.
     *
     * @param  string $name
     * @param  \Closure $closure
     * @return Closure|bool
     */
    public function single(string $name, Closure $closure)
    {
        if ($this->request->hasFile($name)) {
            return $closure($this->request->file($name));
        }

        return false;
    }

    /**
     * Handle chunked upload.
     *
     * @param  string $name
     * @param  \Closure $closure
     * @return Closure|bool
     */
    public function chunks(string $name, Closure $closure)
    {
        if (! $this->request->hasFile($name)) {
            return false;
        }

        $file = $this->request->file($name);

        $chunk = (int) $this->request->input('chunk', false);
        $chunks = (int) $this->request->input('chunks', false);
        $originalName = $this->request->input('name');
        $originalMime = $file->getMimeType();

        $filePath = $this->getChunkPath().'/'.$originalName.'.part';

        $this->removeOldData($filePath);
        $this->appendData($filePath, $file);

        if ($chunk === $chunks - 1) {
            $file = new UploadedFile($filePath, $originalName, $originalMime, UPLOAD_ERR_OK, true);
            //@unlink($file);
            return $closure($file);
        }
    }

    /**
     * Remove old chunks.
     *
     * @param  string $filePath
     * @return void
     */
    private function removeOldData(string $filePath): void
    {
        if ($this->storage->exists($filePath) && ($this->storage->lastModified($filePath) < time() - $this->maxAge)) {
            $this->storage->delete($filePath);
        }
    }

    /**
     * Merge the new chunk with the previous chunks.
     *
     * @param  string $filePathPartial
     * @param  \Illuminate\Http\UploadedFile $file
     * @return void
     */
    private function appendData(string $filePathPartial, UploadedFile $file): void
    {
        try {
            $outFile = fopen($filePathPartial, 'ab');
        } catch (\Exception | \ErrorException $exception) {
            throw new Exception('Failed to open output stream.', 102);
        }

        try {
            $inFile = fopen($file->getPathname(), 'rb');
        } catch (\Exception | \ErrorException $exception) {
            throw new Exception('Failed to open input stream.', 101);
        }

        while ($buff = fread($inFile, 4096)) {
            fwrite($outFile, $buff);
        }

        fclose($outFile);
        fclose($inFile);
    }

    /**
     * Is the request chunked or a single file?
     *
     * @return bool
     */
    public function withChunks(): bool
    {
        return (bool) $this->request->input('chunks', false);
    }
}
