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

    protected function prepareForValidation(): void
    {
        // When forceFormData is used (file uploads), tags may arrive as a
        // comma-separated string or a single string instead of an array.
        // Normalize to a proper array of trimmed, non-empty strings.
        $tags = $this->input('tags');
        if (is_string($tags)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));
            $this->merge(['tags' => $tags]);
        } elseif (is_array($tags)) {
            // Each element might also be comma-separated (user typed "tag1, tag2" and pressed Enter)
            $normalized = [];
            foreach ($tags as $tag) {
                if (is_string($tag)) {
                    foreach (explode(',', $tag) as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $normalized[] = $part;
                        }
                    }
                }
            }
            $this->merge(['tags' => $normalized]);
        }
    }

    public function rules(): array
    {
        $maxSize = $this->user()->max_video_size / 1024;

        $rules = [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'privacy' => 'required|in:public,private,unlisted',
            'age_restricted' => 'boolean',
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
        ];
    }
}
