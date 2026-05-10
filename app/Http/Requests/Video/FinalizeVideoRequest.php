<?php

namespace App\Http\Requests\Video;

use App\Models\Video;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $rawTags = $this->input('tags');
        if ($rawTags !== null) {
            $this->merge(['tags' => Video::normalizeTagsInput($rawTags)]);
        }

        // Hard-force public; regular users cannot publish unlisted/private videos.
        $this->merge(['privacy' => 'public']);
    }

    public function rules(): array
    {
        $rules = [
            'upload_id' => 'required|string|max:64|regex:/^[a-zA-Z0-9_-]+$/',
            'extension' => 'required|string|alpha_num|max:8',
            'original_filename' => 'nullable|string|max:255',
            'title' => 'required|string|min:3|max:200',
            'description' => 'required|string|min:10|max:5000',
            'category_id' => 'required|exists:categories,id',
            'age_restricted' => 'boolean',
            'tags' => 'required|array|min:3|max:20',
            'tags.*' => 'string|min:2|max:50',
        ];

        if ($this->user()->is_admin || $this->user()->is_pro) {
            $rules['scheduled_at'] = 'nullable|date|after:now';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
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
