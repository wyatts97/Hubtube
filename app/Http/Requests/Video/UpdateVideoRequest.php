<?php

namespace App\Http\Requests\Video;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');
        if (is_string($tags)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));
            $this->merge(['tags' => $tags]);
        } elseif (is_array($tags) && count($tags) > 0) {
            // Detect single-character corruption: if most elements are 1 char,
            // the original string was iterated char-by-char by FormData.
            $singleCharCount = count(array_filter($tags, fn ($t) => is_string($t) && mb_strlen(trim($t)) <= 1));
            if (count($tags) >= 3 && $singleCharCount / count($tags) >= 0.5) {
                $joined = implode('', $tags);
                $tags = array_values(array_filter(array_map('trim', explode(',', $joined))));
                $this->merge(['tags' => $tags]);
            } else {
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
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'privacy' => 'sometimes|required|in:public,private,unlisted',
            'age_restricted' => 'boolean',
            'tags' => 'nullable|array|max:20',
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
