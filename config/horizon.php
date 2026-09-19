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
                // video-priority first: the lowest rendition of each upload
                // jumps the queue so videos go live before their other
                // renditions are encoded (see RenditionCoordinator).
                'queue' => ['video-priority', 'video-processing'],
                'balance' => 'simple',
                // Renditions are encoded as separate chunk jobs, so this is also how
                // many chunks of one video can encode at once. Tune to the server's
                // CPU: every worker runs ffmpeg with its own `ffmpeg_threads`
                // setting, and they all compete for the same cores.
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
            // Media Library thumbnails. Its own queue for two reasons: an
            // ffmpeg frame grab blows the default queue's 60s timeout, and a
            // burst from a first `media:thumbnails` pass over an existing
            // library would otherwise starve notifications and alt-text jobs.
            // nice 10 so it always yields to encoding.
            'media-thumbnails' => [
                'connection' => 'redis',
                'queue' => ['media-thumbnails'],
                'balance' => 'simple',
                'maxProcesses' => 2,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 256,
                'tries' => 2,
                'timeout' => 300,
                'nice' => 10,
            ],
            // Media Library compress: re-encoding files that already work, to
            // make them smaller. maxProcesses 1 is the whole answer to "a bulk
            // compress of 200 files must not starve live uploads" — it is
            // strictly serial, and nice 15 means it yields CPU to everything
            // else on the box. tries 1 because a half-written re-encode must
            // never be retried automatically.
            'media-compress' => [
                'connection' => 'redis',
                'queue' => ['media-compress'],
                'balance' => 'simple',
                'maxProcesses' => 1,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 512,
                'tries' => 1,
                'timeout' => 7200,
                'nice' => 15,
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
            'video-processing' => [
                'connection' => 'redis',
                // video-priority first: the lowest rendition of each upload
                // jumps the queue so videos go live before their other
                // renditions are encoded (see RenditionCoordinator).
                'queue' => ['video-priority', 'video-processing'],
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
            'media-thumbnails' => [
                'connection' => 'redis',
                'queue' => ['media-thumbnails'],
                'balance' => 'simple',
                'maxProcesses' => 1,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 256,
                'tries' => 2,
                'timeout' => 300,
                'nice' => 10,
            ],
            'media-compress' => [
                'connection' => 'redis',
                'queue' => ['media-compress'],
                'balance' => 'simple',
                'maxProcesses' => 1,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 512,
                'tries' => 1,
                'timeout' => 7200,
                'nice' => 15,
            ],
        ],
    ],
];
