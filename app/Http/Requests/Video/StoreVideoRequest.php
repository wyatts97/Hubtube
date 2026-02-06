<?php

namespace App\Http\Requests\Video;

use App\Rules\ValidVideoFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Number;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = $this->user()->max_video_size / 1024;

        return [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'privacy' => 'required|in:public,private,unlisted',
            'age_restricted' => 'boolean',
            'is_short' => 'boolean',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:50',
            'video_file' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/x-flv,video/x-ms-wmv',
                "max:{$maxSize}",
                new ValidVideoFile(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'video_file.max' => 'Video file is too large. Maximum size is ' . 
                Number::fileSize($this->user()->max_video_size),
            'video_file.mimetypes' => 'The video must be a valid video file (MP4, MOV, AVI, MKV, WebM, FLV, or WMV).',
        ];
    }
}
