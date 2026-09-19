<?php

return [

    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 7500),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),

            /*
             * retry_after is how long a reserved job may run before the queue
             * assumes the worker died and hands it to someone else. It MUST
             * exceed the longest job timeout on this connection, or a job that
             * is merely slow gets picked up a second time and runs twice.
             *
             * The framework default is 90s, but ProcessVideoJob declares a
             * 3600s timeout — so any transcode over 90 seconds was eligible to
             * be re-reserved and re-run concurrently. TranslateModelJob (300s)
             * had the same exposure.
             *
             * The longest is now CompressMediaFileJob at 7200s: it re-encodes a
             * whole file to H.265, VP9 or AV1 on a queue deliberately niced
             * below everything else, so it can legitimately run for hours.
             * 7500 clears it with room to spare.
             *
             * Trade-off: a worker that genuinely crashes now leaves its job
             * reserved for up to two hours before retry. Crashes are rare;
             * two ffmpeg processes writing the same output file are not
             * survivable.
             */
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 7500),
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
