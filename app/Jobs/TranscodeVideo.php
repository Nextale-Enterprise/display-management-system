<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Models\PlaylistItem;
use App\Transcode\VideoTranscoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TranscodeVideo implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $assetId) {}

    public function handle(VideoTranscoder $transcoder): void
    {
        $asset = MediaAsset::query()->find($this->assetId);
        if (! $asset || $asset->status === 'ready') {
            return;
        }

        $disk = Storage::disk('media');
        $source = tempnam(sys_get_temp_dir(), 'signage-in-');
        $dest = tempnam(sys_get_temp_dir(), 'signage-out-').'.mp4';

        try {
            file_put_contents($source, $disk->get($asset->path));
            $duration = $transcoder->transcode($source, $dest);
            $newPath = 'org/'.$asset->organization_id.'/media/'.Str::uuid().'.mp4';
            $disk->put($newPath, file_get_contents($dest));
            if ($newPath !== $asset->path) {
                $disk->delete($asset->path);
            }
            $asset->update([
                'path' => $newPath,
                'status' => 'ready',
                'duration_ms' => $duration,
                'error' => null,
            ]);
            PlaylistItem::query()
                ->where('media_asset_id', $asset->id)
                ->update(['duration_ms' => $duration]);
        } catch (Throwable $e) {
            $asset->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            @unlink($source);
            @unlink($dest);
        }
    }
}
