<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistItem extends Model
{
    protected $fillable = [
        'playlist_id',
        'media_asset_id',
        'position',
        'duration_ms',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'media_asset_id' => $this->media_asset_id,
            'position' => $this->position,
            'duration_ms' => $this->duration_ms,
            'name' => $this->mediaAsset?->name,
            'type' => $this->mediaAsset?->type,
            'status' => $this->mediaAsset?->status,
        ];
    }
}
