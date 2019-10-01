<?php

namespace JamesSessford\LaravelChunkReceiver;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
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
     * @return void
     */
    public function __construct(Request $request, Filesystem $file)
    {
        $this->request = $request;
        $this->storage = $file;
    }

    /**
     * Get chuck upload path.
     *
     * @return string
     */
    public function getChunkPath()
    {
        $path = config('chunk-receiver.chunk_path');

        if (!$this->storage->isDirectory($path)) {
            $this->storage->makeDirectory($path, 0777, true);
        }

        return $path;
    }

    /**
     * Process uploaded files.
     *
     * @param  string $name
     * @param  \Closure $closure
     * @return array
     */
    public function processUpload($name, Closure $closure)
    {
        $response = [];
        $response['jsonrpc'] = '2.0';

        if ($this->withChunks()) {
            $result = $this->chunks($name, $closure);
        } else {
            $result = $this->single($name, $closure);
        }

        $response['result'] = $result;

        return $response;
    }

    /**
     * Handle whole file
     *
     * @param  string $name
     * @param  \Closure $closure
     * @return void
     */
    public function single($name, Closure $closure)
    {
        if ($this->request->hasFile($name)) {
            return $closure($this->request->file($name));
        }
    }

    /**
     * Handle chunked upload
     *
     * @param  string $name
     * @param  \Closure $closure
     * @return mixed
     */
    public function chunks($name, Closure $closure)
    {
        if (!$this->request->hasFile($name)) {
            return;
        }

        $file = $this->request->file($name);
        $chunk = (int) $this->request->input('chunk', false);
        $chunks = (int) $this->request->input('chunks', false);
        $originalName = $this->request->input('name');

        $filePath = $this->getChunkPath() . '/' . $originalName . '.part';

        $this->removeOldData($filePath);
        $this->appendData($filePath, $file);

        if ($chunk == $chunks) {
            $file = new UploadedFile($filePath, $originalName, 'blob', UPLOAD_ERR_OK, true);
            return $closure($file);
        }
    }

    /**
     * Remove old chunks.
     *
     * @param  string $filePath
     * @return void
     */
    private function removeOldData($filePath)
    {
        if ($this->storage->exists($filePath) && ($this->storage->lastModified($filePath) < time() - $this->maxAge)) {
            $this->storage->delete($filePath);
        }
    }

    /**
     * Merge the new chunk with the previous chunks
     *
     * @param  string $filePathPartial
     * @param  \Illuminate\Http\UploadedFile $file
     * @return void
     */
    private function appendData($filePathPartial, UploadedFile $file)
    {
        if (!$out = @fopen($filePathPartial, 'ab')) {
            throw new Exception('Failed to open output stream.', 102);
        }

        if (!$in = @fopen($file->getPathname(), 'rb')) {
            throw new Exception('Failed to open input stream', 101);
        }

        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }

        @fclose($out);
        @fclose($in);
    }

    /**
     * Is the request chunked or a single file?
     *
     * @return bool
     */
    public function withChunks()
    {
        return (bool) $this->request->input('chunks', false);
    }
}
