<?php

namespace App\Providers;

use App\Transcode\FfmpegTranscoder;
use App\Transcode\VideoTranscoder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VideoTranscoder::class, FfmpegTranscoder::class);
    }

    public function boot(): void
    {
        //
    }
}
