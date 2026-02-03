<?php

namespace App\Http\Requests\Video;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
