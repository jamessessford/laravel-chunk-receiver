<?php

namespace JamesSessford\LaravelChunkReceiver\Tests\Feature\ChunkReceiver;

use Illuminate\Filesystem\Filesystem as FileSystem;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use JamesSessford\LaravelChunkReceiver\Facades\ChunkReceiver;
use JamesSessford\LaravelChunkReceiver\Requests\ChunkReceiverRequest as Request;
use JamesSessford\LaravelChunkReceiver\Tests\TestCase;

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
    public function sending_no_files_returns_fine()
    {
        Route::post('/chunks', function (Request $request) {
            return ChunkReceiver::receive('file', function ($file) use ($request) {
                return response(200);
            });
        });

        $response = $this->post('/chunks', ['file' => 'this is actually just text!']);
        $response->assertStatus(200);
    }

    /** @test */
    public function sending_no_chunks_returns_fine()
    {
        Route::post('/chunks', function (Request $request) {
            return ChunkReceiver::receive('file', function ($file) use ($request) {
                return response(200);
            });
        });

        $response = $this->post('/chunks', ['file' => 'this is actually just text!', 'chunks' => 1, 'chunk' => 0]);
        $response->assertStatus(200);
    }

    /** @test */
    public function a_small_file_can_be_uploaded_in_one()
    {
        Route::post('/chunks', function (Request $request) {
            return ChunkReceiver::receive('file', function ($file) use ($request) {
                $fileSize = filesize($file->getRealPath());

                return response(['file' => $request->file('file')->name, 'size' => $fileSize]);
            });
        });

        $file = UploadedFile::fake()->image('image.jpg')->size(140);
        $fileSize = filesize($file->getRealPath());
        $data = ['file' => 'image.jpg', 'size' => $fileSize];

        $response = $this->post('/chunks', ['file' => $file]);

        $response->assertStatus(200);
        $response->assertJsonFragment($data);
    }

    /** @test */
    public function file_chunk_can_be_uploaded()
    {
        rmdir($this->getChunkDirectory());

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

        $response = $this->post('/chunks', ['file' => $file, 'chunk' => $i, 'chunks' => $fileChunks, 'name' => $fileName]);
        fclose($tmpfile);

        $data = ['result' => null];

        $response->assertStatus(200);
        $response->assertJsonFragment($data);
    }

    /** @test */
    public function file_old_chunks_will_be_deleted()
    {
        $fileName = 'image.jpg';
        $filePath = $this->getSupportDirectory().'/'.$fileName;

        $fileSize = filesize($filePath);
        $chunkSize = (config('chunk-receiver.chunk_size') * 1024);
        $fileChunks = (int) ceil($fileSize / $chunkSize);

        $filesystem = new Filesystem;
        $i = 2;
        $fileBytes = file_get_contents($filePath, false, null, $i * $chunkSize, $chunkSize);

        $touch = touch($this->getChunkDirectory().'/.part', time() - 2000, time() - 2000);

        $tmpfile = tmpfile();
        fwrite($tmpfile, $fileBytes);

        $file = new TestingFile('image.jpg', $tmpfile);
        $files = [];
        $files['file'] = $file;

        $chunkReceiverRequest = new \JamesSessford\LaravelChunkReceiver\Requests\ChunkReceiverRequest([], [
            'chunks' => $fileChunks,
            'chunk' => $i,
            'name' => 'image.jpg',
        ], [], [], $files);
        $receivedFile = new \JamesSessford\LaravelChunkReceiver\ReceivedFile($chunkReceiverRequest, $filesystem);

        $timestamp = time() - 2000;

        $touch = touch($this->getChunkDirectory().'/.part', $timestamp, $timestamp);

        $receivedFile->chunks('file', function ($file) {
        });

        $this->assertGreaterThan($timestamp, filemtime($this->getChunkDirectory().'/.part'));
    }

    /** @test */
    public function chunked_file_can_be_uploaded()
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

        for ($i = 0; $i < $fileChunks; $i++) {
            $fileBytes = file_get_contents($filePath, false, null, $i * $chunkSize, $chunkSize);
            $tmpfile = tmpfile();
            fwrite($tmpfile, $fileBytes);

            $file = new TestingFile('image.jpg', $tmpfile);

            $response = $this->post('/chunks', ['file' => $file, 'chunk' => $i, 'chunks' => $fileChunks, 'name' => $fileName]);

            fclose($tmpfile);
        }

        $data = ['file' => 'image.jpg'];

        $response->assertStatus(200);
        $response->assertJsonFragment($data);
    }

    /** @test */
    public function chunk_without_output_can_fail()
    {
        $fileName = 'image.jpg';
        $filePath = $this->getSupportDirectory().'/'.$fileName;

        $fileSize = filesize($filePath);
        $chunkSize = (config('chunk-receiver.chunk_size') * 1024);
        $fileChunks = (int) ceil($fileSize / $chunkSize);

        $filesystem = new Filesystem;

        $i = 2;
        $fileBytes = file_get_contents($filePath, false, null, $i * $chunkSize, $chunkSize);
        $tmpfile = tmpfile();
        fwrite($tmpfile, $fileBytes);

        $file = new TestingFile('image.jpg', $tmpfile);
        $files = [];
        $files['file'] = $file;

        $chunkReceiverRequest = new \JamesSessford\LaravelChunkReceiver\Requests\ChunkReceiverRequest([], [
            'chunks' => $fileChunks,
            'chunk' => $i,
            'name' => 'image.jpg',
        ], [], [], $files);
        $receivedFile = new \JamesSessford\LaravelChunkReceiver\ReceivedFile($chunkReceiverRequest, $filesystem);

        rmdir($this->getChunkDirectory());

        try {
            $receivedFile->chunks('file', function ($file) {
            });
        } catch (\JamesSessford\LaravelChunkReceiver\Exceptions\Exception $e) {
            $this->assertInstanceOf('JamesSessford\LaravelChunkReceiver\Exceptions\Exception', $e);
            $this->assertEquals(102, $e->getCode());
            $this->assertEquals('Failed to open output stream.', $e->getMessage());
        }
    }

    /** @test */
    public function chunk_without_input_can_fail()
    {
        $fileName = 'image.jpg';
        $filePath = $this->getSupportDirectory().'/'.$fileName;

        $fileSize = filesize($filePath);
        $chunkSize = (config('chunk-receiver.chunk_size') * 1024);
        $fileChunks = (int) ceil($fileSize / $chunkSize);

        $filesystem = new Filesystem;

        $i = 1;
        $fileBytes = file_get_contents($filePath, false, null, $i * $chunkSize, $chunkSize);
        $tmpfile = tmpfile();
        fwrite($tmpfile, $fileBytes);

        $file = new TestingFile('image.jpg', $tmpfile);
        $files = [];
        $files['file'] = $file;

        $chunkReceiverRequest = new \JamesSessford\LaravelChunkReceiver\Requests\ChunkReceiverRequest([], [
            'chunks' => $fileChunks,
            'chunk' => $i,
            'name' => 'image.jpg',
        ], [], [], $files);
        $receivedFile = new \JamesSessford\LaravelChunkReceiver\ReceivedFile($chunkReceiverRequest, $filesystem);

        unlink($file);

        try {
            $receivedFile->chunks('file', function ($file) {
            });
        } catch (\JamesSessford\LaravelChunkReceiver\Exceptions\Exception $e) {
            $this->assertInstanceOf('JamesSessford\LaravelChunkReceiver\Exceptions\Exception', $e);
            $this->assertEquals(101, $e->getCode());
            $this->assertEquals('Failed to open input stream.', $e->getMessage());
        }
    }
}
