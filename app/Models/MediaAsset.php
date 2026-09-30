<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaAsset extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'type',
        'path',
        'status',
        'duration_ms',
        'error',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(PlaylistItem::class);
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'duration_ms' => $this->duration_ms,
            'error' => $this->error,
        ];
    }
}
