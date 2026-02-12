<?php

namespace App\Rules;

use App\Models\Setting;
use App\Services\FfmpegService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ValidVideoFile implements ValidationRule
{
    protected array $allowedCodecs = [
        'h264', 'hevc', 'h265', 'vp8', 'vp9', 'av1', 'mpeg4', 'mpeg2video', 'wmv3', 'vc1'
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile) {
            $fail('The :attribute must be a valid file.');
            return;
        }

        // Check if FFprobe is enabled and available
        $ffmpegEnabled = Setting::get('ffmpeg_enabled', true);
        if (!$ffmpegEnabled) {
            return; // Skip FFprobe validation if FFmpeg is disabled
        }

        $ffprobePath = FfmpegService::ffprobePath();
        if (!$ffprobePath || !is_executable($ffprobePath)) {
            Log::warning('FFprobe not found or not executable, skipping video validation');
            return;
        }

        $filePath = $value->getRealPath();
        
        // Run FFprobe to get file info
        $command = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams %s 2>&1',
            escapeshellcmd($ffprobePath),
            escapeshellarg($filePath)
        );

        $output = shell_exec($command);
        
        if (!$output) {
            $fail('Unable to analyze the video file. Please ensure it is a valid video.');
            return;
        }

        $info = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($info['streams'])) {
            $fail('The :attribute does not appear to be a valid video file.');
            return;
        }

        // Check for video stream
        $hasVideoStream = false;
        $videoCodec = null;
        $duration = 0;

        foreach ($info['streams'] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $hasVideoStream = true;
                $videoCodec = strtolower($stream['codec_name'] ?? '');
                break;
            }
        }

        if (!$hasVideoStream) {
            $fail('The :attribute must contain a video stream.');
            return;
        }

        // Validate codec
        if ($videoCodec && !in_array($videoCodec, $this->allowedCodecs)) {
            $fail("The video codec '{$videoCodec}' is not supported. Supported codecs: " . implode(', ', $this->allowedCodecs));
            return;
        }

        // Get duration from format
        if (isset($info['format']['duration'])) {
            $duration = (float) $info['format']['duration'];
        }

        // Check minimum duration (at least 1 second)
        if ($duration < 1) {
            $fail('The :attribute must be at least 1 second long.');
            return;
        }

        // Check for corrupted files (duration too long or invalid)
        if ($duration > 86400) { // 24 hours max
            $fail('The :attribute duration exceeds the maximum allowed (24 hours).');
            return;
        }
    }

}
