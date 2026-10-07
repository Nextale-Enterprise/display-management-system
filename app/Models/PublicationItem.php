<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class PublicationItem extends Model
{
    protected $fillable = [
        'publication_id',
        'media_asset_id',
        'position',
        'row_index',
        'column_index',
        'duration_ms',
        'name',
        'type',
        'path',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function previewUrl(): string
    {
        if (config('filesystems.disks.media.driver') === 's3') {
            return Storage::disk('media')->temporaryUrl($this->path, now()->addHours(12));
        }

        return URL::temporarySignedRoute('media.file', now()->addHours(12), [
            'publicationItem' => $this->id,
        ]);
    }
}
