<?php

namespace App\Services\Translation;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Decides whether a scheduled translation run is due.
 *
 * `Schedule::command(...)->cron($expr)` needs its expression as a string at
 * registration time, which would mean a database read on every console
 * bootstrap. Instead the command is registered `everyMinute()` with a lazily
 * evaluated `->skip()` closure calling isDueNow(). That keeps registration
 * DB-free, and a frequency change in the admin panel takes effect the next
 * minute with no deploy or cache clear.
 */
final class TranslationSchedule
{
    public const FREQUENCIES = ['disabled', 'hourly', 'daily', 'weekly', 'monthly'];

    public static function frequency(): string
    {
        $value = (string) self::setting('translation_schedule_frequency', config('translation.schedule.default_frequency', 'daily'));

        return in_array($value, self::FREQUENCIES, true) ? $value : 'disabled';
    }

    /** 'HH:MM' in the app timezone. */
    public static function time(): string
    {
        $value = (string) self::setting('translation_schedule_time', config('translation.schedule.default_time', '03:30'));

        return preg_match('/^\d{1,2}:\d{2}$/', $value) ? $value : '03:30';
    }

    /** 0-6 (Sun-Sat) for weekly, 1-28 for monthly. */
    public static function day(): int
    {
        return (int) self::setting('translation_schedule_day', 1);
    }

    public static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    public static function isEnabled(): bool
    {
        return self::frequency() !== 'disabled';
    }

    /**
     * Is this exact minute the scheduled one?
     *
     * Pure aside from settings, so it can be driven with Carbon::setTestNow().
     */
    public static function isDueAt(CarbonInterface $now): bool
    {
        $frequency = self::frequency();

        if ($frequency === 'disabled') {
            return false;
        }

        $now = $now->copy()->setTimezone(self::timezone());
        [$hour, $minute] = array_map('intval', explode(':', self::time()));

        return match ($frequency) {
            'hourly' => $now->minute === $minute,
            'daily' => $now->hour === $hour && $now->minute === $minute,
            'weekly' => $now->dayOfWeek === self::day() && $now->hour === $hour && $now->minute === $minute,
            'monthly' => $now->day === max(1, min(28, self::day())) && $now->hour === $hour && $now->minute === $minute,
            default => false,
        };
    }

    /**
     * Due now, including catch-up.
     *
     * Without catch-up a monthly run missed by a single minute — a reboot,
     * scheduler lag, or the overlap lock still held — is lost for a month.
     */
    public static function isDueNow(?CarbonInterface $now = null): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        $now = ($now ?? Carbon::now())->copy()->setTimezone(self::timezone());

        if (self::isDueAt($now)) {
            return true;
        }

        $lastRun = self::lastRunAt();

        if ($lastRun === null) {
            // Never run: start at the next scheduled minute rather than
            // immediately, so installing the feature doesn't kick off a full
            // catalogue sweep during peak traffic.
            return false;
        }

        $previous = self::previousDueAt($now);

        return $previous !== null && $lastRun->lt($previous);
    }

    /** The most recent scheduled moment at or before $now. */
    public static function previousDueAt(CarbonInterface $now): ?CarbonInterface
    {
        $now = $now->copy()->setTimezone(self::timezone());
        [$hour, $minute] = array_map('intval', explode(':', self::time()));
        $frequency = self::frequency();

        $moment = match ($frequency) {
            'hourly' => $now->copy()->minute($minute)->second(0),
            'daily' => $now->copy()->setTime($hour, $minute),
            'weekly' => $now->copy()->startOfWeek(self::day())->setTime($hour, $minute),
            'monthly' => $now->copy()->day(max(1, min(28, self::day())))->setTime($hour, $minute),
            default => null,
        };

        if ($moment === null) {
            return null;
        }

        // Rolling forward from the current period can land in the future; step
        // back one period when it does.
        if ($moment->gt($now)) {
            $moment = match ($frequency) {
                'hourly' => $moment->subHour(),
                'daily' => $moment->subDay(),
                'weekly' => $moment->subWeek(),
                'monthly' => $moment->subMonthNoOverflow(),
                default => $moment,
            };
        }

        return $moment;
    }

    public static function nextRunAt(?CarbonInterface $now = null): ?CarbonInterface
    {
        if (! self::isEnabled()) {
            return null;
        }

        $now = ($now ?? Carbon::now())->copy()->setTimezone(self::timezone());
        $previous = self::previousDueAt($now);

        if ($previous === null) {
            return null;
        }

        return match (self::frequency()) {
            'hourly' => $previous->copy()->addHour(),
            'daily' => $previous->copy()->addDay(),
            'weekly' => $previous->copy()->addWeek(),
            'monthly' => $previous->copy()->addMonthNoOverflow(),
            default => null,
        };
    }

    public static function lastRunAt(): ?CarbonInterface
    {
        $value = self::setting('translation_last_run_at', null);

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(self::timezone());
        } catch (Throwable) {
            return null;
        }
    }

    /** Human summary for the admin panel. */
    public static function describe(): string
    {
        $tz = self::timezone();
        $time = self::time();

        return match (self::frequency()) {
            'disabled' => 'Automatic translation runs are disabled.',
            'hourly' => 'Every hour at :'.str_pad(explode(':', $time)[1], 2, '0', STR_PAD_LEFT)." past ({$tz})",
            'daily' => "Every day at {$time} ({$tz})",
            'weekly' => 'Every '.Carbon::now()->startOfWeek(self::day())->format('l')." at {$time} ({$tz})",
            'monthly' => 'Day '.max(1, min(28, self::day()))." of each month at {$time} ({$tz})",
            default => 'Unknown schedule.',
        };
    }

    /** Equivalent cron expression, shown read-only in the UI for sanity-checking. */
    public static function cronExpression(): ?string
    {
        [$hour, $minute] = array_map('intval', explode(':', self::time()));

        return match (self::frequency()) {
            'hourly' => "{$minute} * * * *",
            'daily' => "{$minute} {$hour} * * *",
            'weekly' => "{$minute} {$hour} * * ".self::day(),
            'monthly' => "{$minute} {$hour} ".max(1, min(28, self::day())).' * *',
            default => null,
        };
    }

    protected static function setting(string $key, mixed $default): mixed
    {
        try {
            return Setting::get($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}
