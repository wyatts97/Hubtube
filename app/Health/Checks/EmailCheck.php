<?php

namespace App\Health\Checks;

use App\Services\EmailService;
use FinityLabs\FinMail\Enums\EmailStatus;
use FinityLabs\FinMail\Models\EmailTemplate;
use FinityLabs\FinMail\Models\SentEmail;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Email fails quietly: a send that errors is logged, and the visitor is still
 * told "we've emailed you". This check is where it shows — mail not set up,
 * no sender address, a template the app sends missing or switched off, or
 * sends that failed in the last day.
 */
class EmailCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        try {
            if (! EmailService::isMailConfigured()) {
                return $result->failed('Mail is not set up (Settings → Integrations), so no email is sent.');
            }

            if (! config('mail.from.address')) {
                return $result->failed('No sender address is set in Settings → Integrations.');
            }

            $available = EmailTemplate::active()->whereIn('key', EmailService::CORE_TEMPLATES)->pluck('key')->all();
            $missing = array_values(array_diff(EmailService::CORE_TEMPLATES, $available));
            if ($missing) {
                return $result->failed('Missing or inactive email templates: '.implode(', ', $missing).'.');
            }

            $failed = SentEmail::where('status', EmailStatus::Failed)
                ->where('created_at', '>=', now()->subDay())
                ->count();
            $result->shortSummary("{$failed} failed today");

            if ($failed > 0) {
                return $result->failed("{$failed} email(s) failed to send in the last 24 hours. See Emails → Sent Emails for the error.");
            }

            return $result->ok();
        } catch (Throwable $e) {
            return $result->failed("Could not check email: {$e->getMessage()}");
        }
    }
}
