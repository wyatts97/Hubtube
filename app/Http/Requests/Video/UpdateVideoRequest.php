<?php

namespace App\Http\Requests\Video;

use App\Models\Video;
use App\Rules\KnownTags;
use App\Support\VideoPrivacy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVideoRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'age_restricted' => 'boolean',
            'privacy' => ['sometimes', 'required', 'string', Rule::in(VideoPrivacy::allowedFor($this->user(), $this->route('video')))],
            'tags' => ['nullable', 'array', 'max:20', new KnownTags],
            'tags.*' => 'string|max:50',
            'geo_blocked_countries' => 'nullable|array',
            'geo_blocked_countries.*' => 'string|size:2',
            'monetization_enabled' => 'boolean',
            'price' => 'nullable|numeric|min:0|max:1000',
            'rent_price' => 'nullable|numeric|min:0|max:100',
            'thumbnail' => 'nullable|image|max:5120',
        ];
    }
}
