<?php

namespace App\Transcode;

interface VideoTranscoder
{
    public function transcode(string $sourcePath, string $destPath): int;
}
