<?php

namespace App\Http\Requests\Video;

use App\Rules\KnownTags;
use App\Models\Video;
use App\Rules\ValidVideoFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Number;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rawTags = $this->input('tags');
        if ($rawTags !== null) {
            $this->merge(['tags' => Video::normalizeTagsInput($rawTags)]);
        }

        // Privacy is hard-forced server-side. Regular users only publish public videos;
        // admin-only privacy management lives in the Filament panel.
        $this->merge(['privacy' => 'public']);
    }

    public function rules(): array
    {
        $maxSize = $this->user()->max_video_size / 1024;

        $rules = [
            'title' => 'required|string|min:3|max:200',
            'description' => 'required|string|min:10|max:5000',
            'category_id' => 'required|exists:categories,id',
            'age_restricted' => 'boolean',
            'tags' => ['required', 'array', 'min:3', 'max:20', new KnownTags()],
            'tags.*' => 'string|min:2|max:50',
            'video_file' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/x-flv,video/x-ms-wmv',
                "max:{$maxSize}",
                new ValidVideoFile(),
            ],
        ];

        // Only admin/pro users can schedule videos
        if ($this->user()->is_admin || $this->user()->is_pro) {
            $rules['scheduled_at'] = 'nullable|date|after:now';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'video_file.max' => 'Video file is too large. Maximum size is ' . 
                Number::fileSize($this->user()->max_video_size),
            'video_file.mimetypes' => 'The video must be a valid video file (MP4, MOV, AVI, MKV, WebM, FLV, or WMV).',
            'title.required' => 'A title is required.',
            'title.min' => 'Title must be at least 3 characters.',
            'description.required' => 'A description is required.',
            'description.min' => 'Description must be at least 10 characters.',
            'category_id.required' => 'Please select a category.',
            'category_id.exists' => 'The selected category is invalid.',
            'tags.required' => 'Please add at least 3 tags.',
            'tags.min' => 'Please add at least 3 tags.',
            'tags.max' => 'You can add up to 20 tags.',
        ];
    }
}
