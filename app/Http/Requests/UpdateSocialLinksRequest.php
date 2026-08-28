<?php

namespace App\Http\Requests;

use App\Rules\SocialLinkUrl;
use App\Services\SocialLinkService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSocialLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // An empty row is how the UI represents "not filled in yet"; drop those
        // instead of failing the whole form on them.
        $links = $this->input('social_links');

        if (is_array($links)) {
            $this->merge([
                'social_links' => array_values(array_filter(
                    $links,
                    fn ($link) => is_array($link) && trim((string) ($link['url'] ?? '')) !== '',
                )),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'social_links' => ['present', 'array', 'max:'.SocialLinkService::MAX_LINKS],
            'social_links.*' => ['array'],
            'social_links.*.platform' => [
                'required',
                'string',
                Rule::in(array_keys(config('social_links'))),
            ],
            'social_links.*.label' => ['nullable', 'string', 'max:40'],

            // The URL rule needs its sibling platform to pick the right host
            // allowlist, so it is built per-index rather than declared once.
            'social_links.*.url' => ['required', 'string', 'max:300'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('social_links', []) as $index => $link) {
                $platform = $link['platform'] ?? null;
                $url = $link['url'] ?? null;

                // Platform validity and URL presence are already covered above;
                // only run the host check when both are usable.
                if (! is_string($platform) || ! is_string($url) || $url === '') {
                    continue;
                }

                (new SocialLinkUrl($platform))->validate(
                    "social_links.{$index}.url",
                    $url,
                    fn ($message) => $validator->errors()->add("social_links.{$index}.url", $message),
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'social_links.max' => 'You can add up to '.SocialLinkService::MAX_LINKS.' links.',
            'social_links.*.platform.in' => 'Unsupported link type.',
        ];
    }
}
