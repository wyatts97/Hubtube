<?php

namespace App\Services\Encoding;

use App\Models\Setting;

/**
 * Reads what is actually inside a media file.
 *
 * Extracted for storage reclaim, which has to prove a re-encode is sound
 * before it is offered for review: same length, real video stream, plausible
 * size. It goes through FfmpegRunner like every other command so tests can
 * swap in the fake rather than needing ffmpeg installed.
 */
class MediaProbe
{
    public function __construct(
        protected FfmpegRunner $runner,
    ) {}

    /**
     * @return array{duration_ms: int, width: int, height: int, has_video: bool, has_audio: bool}|null
     *                                                                                                 Null when the file cannot be probed at all.
     */
    public function inspect(string $absolutePath): ?array
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $commands = new FfmpegCommands(Setting::getAll());

        [$exitCode, $output] = $this->runner->run(
            $commands->ffprobe().' -v quiet -print_format json -show_format -show_streams '.escapeshellarg($absolutePath),
            $commands->timeout(),
        );

        if ($exitCode !== 0 || $output === '') {
            return null;
        }

        $info = json_decode($output, true);

        if (! is_array($info)) {
            return null;
        }

        $result = [
            // Milliseconds, because the reclaim tolerance is sub-second.
            'duration_ms' => (int) round(((float) ($info['format']['duration'] ?? 0)) * 1000),
            'width' => 0,
            'height' => 0,
            'has_video' => false,
            'has_audio' => false,
        ];

        foreach ($info['streams'] ?? [] as $stream) {
            $type = $stream['codec_type'] ?? '';

            if ($type === 'audio') {
                $result['has_audio'] = true;

                continue;
            }

            if ($type !== 'video' || $result['has_video']) {
                continue;
            }

            $result['has_video'] = true;
            $result['width'] = (int) ($stream['width'] ?? 0);
            $result['height'] = (int) ($stream['height'] ?? 0);
        }

        return $result;
    }
}
