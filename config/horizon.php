<?php

use Illuminate\Support\Str;

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => 'horizon',
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'),
    'middleware' => ['web', 'auth'],
    'waits' => [
        'redis:default' => 60,
    ],
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    'silenced' => [],
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],
    'fast_termination' => false,
    'memory_limit' => 64,
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'video-processing' => [
                'connection' => 'redis',
                'queue' => ['video-processing'],
                'balance' => 'simple',
                // Was 3. Each job runs its whole pipeline (probe, thumbnails, sprites,
                // watermark, transcode, HLS, cloud upload) serially in one process with
                // a 3600s timeout, so a burst of uploads or one long video can starve the
                // queue for up to an hour with too few workers. Raised modestly to reduce
                // that risk — tune further to the server's actual CPU core count, since
                // each worker runs ffmpeg with its own `ffmpeg_threads` setting and workers
                // compete for the same CPU.
                'maxProcesses' => 5,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 512,
                'tries' => 3,
                'timeout' => 3600,
                'nice' => 0,
            ],
            // A full translations:run sweeps every locale and can take many
            // minutes. On the default queue (60s timeout) it was killed with
            // TimeoutExceededException, and while running it would occupy a
            // worker other jobs need. Same isolation rationale as the two
            // supervisors around it.
            'translations' => [
                'connection' => 'redis',
                'queue' => ['translations'],
                'balance' => 'simple',
                'maxProcesses' => 2,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 256,
                // The sweep is idempotent and resumable, so a retry would only
                // repeat provider calls that already succeeded.
                'tries' => 1,
                'timeout' => 3600,
                'nice' => 0,
            ],
            // Ad creatives are small (a few MB) and their HLS conversion is quick —
            // kept on its own queue/supervisor so these tiny jobs never queue behind
            // long-running video-processing jobs on the shared queue.
            'ad-processing' => [
                'connection' => 'redis',
                'queue' => ['ad-processing'],
                'balance' => 'simple',
                'maxProcesses' => 2,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 256,
                'tries' => 2,
                'timeout' => 300,
                'nice' => 0,
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
            'video-processing' => [
                'connection' => 'redis',
                'queue' => ['video-processing'],
                'balance' => 'simple',
                'maxProcesses' => 1,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 512,
                'tries' => 3,
                'timeout' => 3600,
                'nice' => 0,
            ],
            'ad-processing' => [
                'connection' => 'redis',
                'queue' => ['ad-processing'],
                'balance' => 'simple',
                'maxProcesses' => 1,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 256,
                'tries' => 2,
                'timeout' => 300,
                'nice' => 0,
            ],
            'translations' => [
                'connection' => 'redis',
                'queue' => ['translations'],
                'balance' => 'simple',
                'maxProcesses' => 1,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 256,
                'tries' => 1,
                'timeout' => 3600,
                'nice' => 0,
            ],
        ],
    ],
];
