<?php

use App\Jobs\ProcessVideoJob;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| ProcessVideoJob — one failed rendition must not discard the others
|--------------------------------------------------------------------------
|
| FFmpeg is simulated: the fake writes a plausible output for the qualities
| listed in $succeed and fails everything else, both in the multi-output
| pass and in the per-quality retry.
|
*/

function fakeTranscodeJob(Video $video, string $outputDir, array $succeed): ProcessVideoJob
{
    return new class($video, $outputDir, $succeed) extends ProcessVideoJob
    {
        public function __construct(Video $video, public string $outputDir, public array $succeed)
        {
            parent::__construct($video);
        }

        protected function getFFmpegPath(): string
        {
            return 'ffmpeg';
        }

        protected function runCommand(string $cmd): array
        {
            foreach ($this->succeed as $quality) {
                if (str_contains($cmd, "{$quality}.mp4")) {
                    file_put_contents("{$this->outputDir}/{$quality}.mp4", str_repeat('x', 20480));
                }
            }

            return [1, 'simulated ffmpeg failure'];
        }

        public function transcode(array $targets): array
        {
            return $this->transcodeAllQualities('input.mp4', $this->outputDir, $targets, false);
        }

        public function reason(): ?string
        {
            return $this->partialRenditionReason();
        }
    };
}

test('successful renditions are kept when another rendition fails', function () {
    $video = Video::factory()->create();
    $outputDir = sys_get_temp_dir().'/hubtube-partial-'.uniqid();
    mkdir($outputDir, 0755, true);

    $job = fakeTranscodeJob($video, $outputDir, succeed: ['360p']);

    $qualities = $job->transcode([
        '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '800k'],
        '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2800k'],
    ]);

    expect($qualities)->toBe(['360p']);
    expect($job->reason())->toStartWith('Some renditions failed: 720p');

    array_map('unlink', glob("{$outputDir}/*"));
    rmdir($outputDir);
});

test('no partial reason is recorded when every rendition succeeds', function () {
    $video = Video::factory()->create();
    $outputDir = sys_get_temp_dir().'/hubtube-partial-'.uniqid();
    mkdir($outputDir, 0755, true);

    $job = fakeTranscodeJob($video, $outputDir, succeed: ['360p', '720p']);

    $qualities = $job->transcode([
        '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '800k'],
        '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2800k'],
    ]);

    expect($qualities)->toBe(['360p', '720p']);
    expect($job->reason())->toBeNull();

    array_map('unlink', glob("{$outputDir}/*"));
    rmdir($outputDir);
});
