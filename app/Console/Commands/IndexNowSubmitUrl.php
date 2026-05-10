<?php

namespace App\Console\Commands;

use App\Services\IndexNowService;
use Illuminate\Console\Command;

class IndexNowSubmitUrl extends Command
{
    protected $signature = 'indexnow:submit-url {url : Absolute URL to submit}';

    protected $description = 'Submit a single URL to IndexNow';

    public function handle(IndexNowService $service): int
    {
        if (!$service->isEnabled()) {
            $this->warn('IndexNow is disabled or missing key. Configure it in Admin → SEO Settings → Search Indexing.');
            return self::FAILURE;
        }

        $url = (string) $this->argument('url');
        $ok = $service->submitUrl($url);

        if ($ok) {
            $this->info("Submitted: {$url}");
            return self::SUCCESS;
        }

        $this->error("Submission failed for: {$url} (see logs and search_index_submissions table)");
        return self::FAILURE;
    }
}
