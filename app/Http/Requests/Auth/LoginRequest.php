<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AdminLogger;
use App\Services\WordPressPasswordHasher;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $loginField = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Try standard Laravel auth first (works for native bcrypt hashes)
        // Wrapped in try-catch because Laravel 11 + PHP 8.4 throws RuntimeException
        // when Hash::check() encounters a non-bcrypt hash (e.g. WordPress phpass $P$B...)
        try {
            if (Auth::attempt([$loginField => $this->login, 'password' => $this->password], $this->boolean('remember'))) {
                RateLimiter::clear($this->throttleKey());

                if (Auth::user()->is_admin) {
                    AdminLogger::auth('Admin login', ['ip' => $this->ip()]);
                }

                return;
            }
        } catch (\RuntimeException) {
            // Non-bcrypt hash detected — fall through to WordPress auth
        }

        // If standard auth failed, check for WordPress password hashes
        if ($this->attemptWordPressAuth($loginField)) {
            RateLimiter::clear($this->throttleKey());
            return;
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.failed'),
        ]);
    }

    /**
     * Attempt authentication using WordPress password hashes.
     * If successful, rehash the password to native Laravel bcrypt.
     */
    private function attemptWordPressAuth(string $loginField): bool
    {
        // Look up the user's raw password hash via DB::table to bypass Eloquent's hashed cast
        $row = DB::table('users')
            ->where($loginField, $this->login)
            ->select(['id', 'password'])
            ->first();

        // DEBUG: trace the full WP auth flow (remove after confirming login works)
        Log::info('WP Auth Debug', [
            'login_field' => $loginField,
            'login_value' => $this->login,
            'user_found' => (bool) $row,
            'stored_hash_prefix' => $row ? substr($row->password, 0, 10) : null,
            'stored_hash_length' => $row ? strlen($row->password) : null,
            'is_wp_hash' => $row ? WordPressPasswordHasher::isWordPressHash($row->password) : false,
        ]);

        if (!$row || !WordPressPasswordHasher::isWordPressHash($row->password)) {
            return false;
        }

        // Verify the plaintext password against the WP hash
        $wpHasher = new WordPressPasswordHasher();
        $checkResult = $wpHasher->check($this->password, $row->password);

        Log::info('WP Auth Hash Check', [
            'user_id' => $row->id,
            'check_result' => $checkResult,
            'hash_type' => str_starts_with($row->password, '$wp$') ? 'wp_bcrypt' : 'phpass',
            'sha384_preview' => substr(hash('sha384', $this->password), 0, 20) . '...',
        ]);

        if (!$checkResult) {
            return false;
        }

        // Password verified! Rehash to native Laravel bcrypt
        DB::table('users')
            ->where('id', $row->id)
            ->update(['password' => Hash::make($this->password)]);

        // Now log them in
        $user = User::find($row->id);
        Auth::login($user, $this->boolean('remember'));

        AdminLogger::auth('WordPress password migrated to bcrypt', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => $this->ip(),
        ]);

        return true;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')) . '|' . $this->ip());
    }
}
