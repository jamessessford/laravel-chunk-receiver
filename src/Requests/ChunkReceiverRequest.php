<?php

namespace JamesSessford\LaravelChunkReceiver\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChunkReceiverRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'file' => 'required|max:' . (config('chunk-receiver.chunk_size') * 1024),
            'chunks' => 'nullable|numeric',
            'chunk' => 'nullable|numeric',
            'name' => 'nullable|string',
        ];
    }
}
