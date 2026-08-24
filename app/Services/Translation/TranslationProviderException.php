<?php

namespace App\Services\Translation;

use RuntimeException;
use Throwable;

/**
 * Provider-agnostic translation failure.
 *
 * Replaces the old `str_contains($e->getMessage(), '429')` sniffing, which only
 * understood one provider's error text. Callers branch on `isRetryable()` and
 * `isFatal()` instead of parsing messages.
 */
class TranslationProviderException extends RuntimeException
{
    public const RATE_LIMITED = 'rate_limited';

    public const UNAUTHORIZED = 'unauthorized';

    public const UNAVAILABLE = 'unavailable';

    public const UNSUPPORTED_LANGUAGE = 'unsupported_language';

    public const FAILED = 'failed';

    public function __construct(
        string $message,
        public readonly string $reason = self::FAILED,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function rateLimited(string $message, ?int $status = 429, ?Throwable $previous = null): self
    {
        return new self($message, self::RATE_LIMITED, $status, $previous);
    }

    public static function unauthorized(string $message, ?int $status = 403, ?Throwable $previous = null): self
    {
        return new self($message, self::UNAUTHORIZED, $status, $previous);
    }

    public static function unavailable(string $message, ?int $status = null, ?Throwable $previous = null): self
    {
        return new self($message, self::UNAVAILABLE, $status, $previous);
    }

    public static function unsupportedLanguage(string $message, ?int $status = 400, ?Throwable $previous = null): self
    {
        return new self($message, self::UNSUPPORTED_LANGUAGE, $status, $previous);
    }

    public static function failed(string $message, ?int $status = null, ?Throwable $previous = null): self
    {
        return new self($message, self::FAILED, $status, $previous);
    }

    public function isRateLimited(): bool
    {
        return $this->reason === self::RATE_LIMITED;
    }

    /**
     * Worth waiting and trying again — the request itself was fine.
     */
    public function isRetryable(): bool
    {
        return in_array($this->reason, [self::RATE_LIMITED, self::UNAVAILABLE], true);
    }

    /**
     * Retrying cannot help: bad credentials, or a language the provider has no
     * model for. Callers must abort the locale rather than burn the retry
     * budget on every single string in the catalogue.
     */
    public function isFatal(): bool
    {
        return in_array($this->reason, [self::UNAUTHORIZED, self::UNSUPPORTED_LANGUAGE], true);
    }
}
