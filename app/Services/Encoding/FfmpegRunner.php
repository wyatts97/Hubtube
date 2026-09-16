<?php

namespace App\Services\Encoding;

use App\Services\FfmpegService;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs ffmpeg/ffprobe command lines.
 *
 * Every encoding job goes through this one class, so tests can swap in a fake
 * that writes the expected output files instead of needing FFmpeg installed.
 *
 * Symfony Process gives each command its own timeout and terminates the whole
 * process tree when it fires, instead of leaving orphaned ffmpeg children for
 * the queue worker's outer timeout to strand.
 */
class FfmpegRunner
{
    public function isAvailable(): bool
    {
        return FfmpegService::isAvailable();
    }

    /**
     * $onProgress receives the seconds of output written so far. It only fires
     * for commands that include `-progress pipe:1`.
     *
     * @param  callable(float):void|null  $onProgress
     * @return array{0: int, 1: string} Exit code and combined output.
     */
    public function run(string $command, int $timeoutSeconds, ?callable $onProgress = null): array
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeoutSeconds > 0 ? $timeoutSeconds : null);

        $pending = '';

        $callback = $onProgress === null ? null : function (string $type, string $buffer) use (&$pending, $onProgress): void {
            if ($type !== Process::OUT) {
                return;
            }

            // Chunks can end mid-line; only act on complete lines.
            $pending .= $buffer;
            while (($newline = strpos($pending, "\n")) !== false) {
                $line = trim(substr($pending, 0, $newline));
                $pending = substr($pending, $newline + 1);

                // out_time_us is microseconds; so, despite its name, is out_time_ms.
                if (preg_match('/^out_time_(?:us|ms)=(\d+)$/', $line, $m)) {
                    $onProgress((int) $m[1] / 1_000_000);
                }
            }
        };

        try {
            $process->run($callback);
        } catch (ProcessTimedOutException) {
            return [1, 'Command timed out after '.$timeoutSeconds.'s'];
        }

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return [$process->getExitCode() ?? 1, $output];
    }
}
