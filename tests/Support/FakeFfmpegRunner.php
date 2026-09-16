<?php

namespace Tests\Support;

use App\Services\Encoding\FfmpegRunner;

/**
 * Stands in for ffmpeg/ffprobe so the encoding pipeline can run in tests.
 *
 * ffprobe returns the configured source description; every other command
 * "succeeds" by writing a plausible file at its output path (the last
 * argument), unless $failWhen says otherwise.
 */
class FakeFfmpegRunner extends FfmpegRunner
{
    /** @var list<string> */
    public array $commands = [];

    /** @var (callable(string): bool)|null */
    public $failWhen = null;

    /** @var (callable(string): void)|null Called before each command runs. */
    public $onRun = null;

    public function __construct(
        public float $duration = 60.0,
        public int $width = 1920,
        public int $height = 1080,
        public bool $hasAudio = true,
    ) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(string $command, int $timeoutSeconds, ?callable $onProgress = null): array
    {
        $this->commands[] = $command;

        if ($this->onRun) {
            ($this->onRun)($command);
        }

        if (str_contains($command, 'ffprobe')) {
            return [0, json_encode([
                'format' => ['duration' => (string) $this->duration],
                'streams' => array_values(array_filter([
                    ['codec_type' => 'video', 'width' => $this->width, 'height' => $this->height],
                    $this->hasAudio ? ['codec_type' => 'audio'] : null,
                ])),
            ])];
        }

        if (str_contains($command, '-encoders')) {
            return [0, ''];
        }

        if ($this->failWhen && ($this->failWhen)($command)) {
            return [1, 'simulated ffmpeg failure'];
        }

        $output = $this->outputPath($command);

        if ($output === null) {
            return [0, ''];
        }

        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }

        if (str_ends_with($output, '.m3u8')) {
            file_put_contents($output, "#EXTM3U\n");
            file_put_contents(dirname($output).'/segment_000.ts', str_repeat('t', 4096));
        } else {
            file_put_contents($output, str_repeat('x', 20480));
        }

        if ($onProgress) {
            $onProgress(1.0);
            $onProgress(2.0);
        }

        return [0, ''];
    }

    /** Commands whose last argument matched $needle. */
    public function commandsFor(string $needle): array
    {
        return array_values(array_filter($this->commands, fn ($c) => str_contains($c, $needle)));
    }

    protected function outputPath(string $command): ?string
    {
        $command = preg_replace('/\s+2>&1\s*$/', '', trim($command));

        // escapeshellarg() quotes with ' on POSIX and " on Windows.
        if (preg_match('/([\'"])([^\'"]+)\1$/', $command, $m)) {
            return $m[2];
        }

        return null;
    }
}
