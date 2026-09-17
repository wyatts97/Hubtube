<?php

namespace App\Rules;

use App\Services\RegistrationGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects email addresses on a blocked or disposable domain.
 *
 * The message deliberately does not say which list matched, so the form
 * cannot be used to enumerate the blocklist.
 */
class AllowedEmailDomain implements ValidationRule
{
    public function __construct(
        protected ?RegistrationGuard $guard = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $guard = $this->guard ?? app(RegistrationGuard::class);

        if (! $guard->emailAllowed(is_string($value) ? $value : null)) {
            $fail('Please use a different email address. This email provider is not accepted.');
        }
    }
}
