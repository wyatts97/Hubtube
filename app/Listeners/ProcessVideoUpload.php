<?php

namespace App\Listeners;

use App\Events\VideoUploaded;
use App\Jobs\ProcessVideoJob;

class ProcessVideoUpload
{
    public function handle(VideoUploaded $event): void
    {
        ProcessVideoJob::dispatch($event->video)->onQueue('video-processing');
    }
}
