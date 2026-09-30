<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playlist extends Model
{
    protected $fillable = ['organization_id', 'name'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('position');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function present(): array
    {
        $this->loadMissing('items.mediaAsset');

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'published_generation' => (int) ($this->publications()->max('generation') ?? 0),
            'items' => $this->items->map(fn (PlaylistItem $item) => $item->present())->values(),
        ];
    }
}
