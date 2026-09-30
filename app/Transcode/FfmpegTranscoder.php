<?php

namespace App\Transcode;

use RuntimeException;
use Symfony\Component\Process\Process;

class FfmpegTranscoder implements VideoTranscoder
{
    public function transcode(string $sourcePath, string $destPath): int
    {
        if (! $this->binaryExists('ffmpeg') || ! $this->binaryExists('ffprobe')) {
            throw new RuntimeException('ffmpeg is not installed');
        }

        $ffmpeg = new Process([
            'ffmpeg', '-y', '-i', $sourcePath,
            '-vf', "scale='min(1920,iw)':'min(1080,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2",
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-preset', 'veryfast', '-crf', '23',
            '-c:a', 'aac', '-movflags', '+faststart',
            $destPath,
        ]);
        $ffmpeg->setTimeout(3600);
        $ffmpeg->mustRun();

        $probe = new Process([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $destPath,
        ]);
        $probe->mustRun();
        $seconds = (float) trim($probe->getOutput());

        return max(1, (int) round($seconds * 1000));
    }

    private function binaryExists(string $binary): bool
    {
        $process = new Process(['which', $binary]);
        $process->run();

        return $process->isSuccessful();
    }
}
