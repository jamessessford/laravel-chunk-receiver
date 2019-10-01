<?php

namespace JamesSessford\LaravelChunkReceiver\Tests\Feature\ChunkReceiver;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Filesystem\Filesystem as FileSystem;
use JamesSessford\LaravelChunkReceiver\Tests\TestCase;
use JamesSessford\LaravelChunkReceiver\Facades\ChunkReceiver;
use JamesSessford\LaravelChunkReceiver\Requests\ChunkReceiverRequest as Request;

final class IntegrationTest extends TestCase
{
    /** @test */
    public function sending_nothing_will_302()
    {
        Route::post('/chunks', function (Request $request) {
            return ChunkReceiver::receive('file', function ($file) use ($request) {
                return response(200);
            });
        });

        $response = $this->post('/chunks');
        $response->assertStatus(302);
        $response->assertSessionHas('errors');
    }

    /** @test */
    public function a_small_file_can_be_uploaded_in_one()
    {
        Route::post('/chunks', function (Request $request) {
            return ChunkReceiver::receive('file', function ($file) use ($request) {
                return response(['file' => $request->file('file')->name]);
            });
        });

        $file = UploadedFile::fake()->image('image.jpg')->size(140);

        $data = ['file' => 'image.jpg'];

        $response = $this->post('/chunks', ['file' => $file]);
        $response->assertStatus(200);
        $response->assertJsonFragment($data);
    }

    /** @test */
    public function file_chunk_can_be_uploaded()
    {
        Route::post('/chunks', function (Request $request) {
            return ChunkReceiver::receive('file', function ($file) use ($request) {
                return response(['file' => $request->file('file')->name]);
            });
        });

        $fileName = 'image.jpg';
        $filePath = $this->getSupportDirectory().'/'.$fileName;

        $fileSize = filesize($filePath);
        $chunkSize = (config('chunk-receiver.chunk_size') * 1024);
        $fileChunks = (int) ceil($fileSize / $chunkSize);

        $i = 0;
        $fileBytes = file_get_contents($filePath, false, null, $i * $chunkSize, $chunkSize);
        $tmpfile = tmpfile();
        fwrite($tmpfile, $fileBytes);

        $file = new TestingFile('image.jpg', $tmpfile);

        $response = $this->post('/chunks', ['file' => $file, 'chunk' => $i, 'chunks' => $fileChunks - 1, 'name' => $fileName]);
        fclose($tmpfile);

        $data = ['result' => null];

        $response->assertStatus(200);
        $response->assertJsonFragment($data);
    }

    /** @test */
    public function chunked_file_can_be_uploaded()
    {
        Route::post('/chunks', function (Request $request) {
            return ChunkReceiver::receive('file', function ($file) use ($request) {
                Storage::disk('public')->putFileAs('temp/files/', $file, 'image.jpg');

                return response(['file' => $request->file('file')->name]);
            });
        });

        $fileName = 'image.jpg';
        $filePath = $this->getSupportDirectory().'/'.$fileName;

        $fileSize = filesize($filePath);
        $chunkSize = (config('chunk-receiver.chunk_size') * 1024);
        $fileChunks = (int) ceil($fileSize / $chunkSize);

        for ($i = 0; $i < $fileChunks; $i++) {
            $fileBytes = file_get_contents($filePath, false, null, $i * $chunkSize, $chunkSize);

            $tmpfile = tmpfile();
            fwrite($tmpfile, $fileBytes);

            $file = new TestingFile('image.jpg', $tmpfile);

            $response = $this->post('/chunks', ['file' => $file, 'chunk' => $i, 'chunks' => $fileChunks - 1, 'name' => $fileName]);
            fclose($tmpfile);
        }

        $data = ['file' => 'image.jpg'];

        $response->assertStatus(200);
        $response->assertJsonFragment($data);
    }

    /** @test */
    public function chunk_receiver()
    {
        $fileName = 'image.jpg';
        $filePath = $this->getSupportDirectory().'/'.$fileName;

        $fileSize = filesize($filePath);
        $chunkSize = (config('chunk-receiver.chunk_size') * 1024);
        $fileChunks = (int) ceil($fileSize / $chunkSize);

        $filesystem = new Filesystem;

        $i = 0;
        $fileBytes = file_get_contents($filePath, false, null, $i * $chunkSize, $chunkSize);
        $tmpfile = tmpfile();
        fwrite($tmpfile, $fileBytes);

        $file = new TestingFile('image.jpg', $tmpfile);
        $files = [];
        $files['file'] = $file;

        $chunkReceiverRequest = new \JamesSessford\LaravelChunkReceiver\Requests\ChunkReceiverRequest([], [
            'chunks' => $fileChunks,
            'chunk' => 1,
            'name' => 'image.jpg',
        ], [], [], $files);
        $receivedFile = new \JamesSessford\LaravelChunkReceiver\ReceivedFile($chunkReceiverRequest, $filesystem);
        $receivedFile->processUpload('file', function ($file) {
        });

        $i = 1;
        $fileBytes = file_get_contents($filePath, false, null, $i * $chunkSize, $chunkSize);
        $tmpfile = tmpfile();
        fwrite($tmpfile, $fileBytes);

        $file = new TestingFile('image.jpg', $tmpfile);
        $files = [];
        $files['file'] = $file;

        $chunkReceiverRequest = new \JamesSessford\LaravelChunkReceiver\Requests\ChunkReceiverRequest([], [
            'chunks' => $fileChunks,
            'chunk' => 2,
            'name' => 'image.jpg',
        ], [], [], $files);
        $receivedFile = new \JamesSessford\LaravelChunkReceiver\ReceivedFile($chunkReceiverRequest, $filesystem);

        unlink($file);

        try {
            $receivedFile->chunks('file', function ($file) {
            });
        } catch (\JamesSessford\LaravelChunkReceiver\Exceptions\Exception $e) {
            $this->assertInstanceOf('JamesSessford\LaravelChunkReceiver\Exceptions\Exception', $e);
        }
    }
}
