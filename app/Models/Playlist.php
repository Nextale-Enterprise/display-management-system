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
        return $this->hasMany(PlaylistItem::class)->orderBy('row_index')->orderBy('column_index');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function present(): array
    {
        $this->loadMissing('items.mediaAsset');
        $latest = $this->publications()->with('items')->orderByDesc('generation')->first();
        $publishedItems = $latest?->items ?? collect();
        $draftSignature = $this->items->map(fn (PlaylistItem $item) => [
            $item->row_index,
            $item->column_index,
            $item->media_asset_id,
            $item->duration_ms,
        ])->values()->all();
        $liveSignature = $publishedItems->map(fn (PublicationItem $item) => [
            $item->row_index,
            $item->column_index,
            $item->media_asset_id,
            $item->duration_ms,
        ])->values()->all();

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'published_generation' => (int) ($latest->generation ?? 0),
            'draft_item_count' => $this->items->count(),
            'published_item_count' => $publishedItems->count(),
            'unpublished_changes' => $draftSignature !== $liveSignature,
            'items' => $this->items->map(fn (PlaylistItem $item) => $item->present())->values(),
            'live_items' => $publishedItems->map(fn (PublicationItem $item) => [
                'media_asset_id' => $item->media_asset_id,
                'name' => $item->name,
                'type' => $item->type,
                'position' => $item->position,
                'row_index' => (int) $item->row_index,
                'column_index' => (int) $item->column_index,
                'duration_ms' => (int) $item->duration_ms,
                'url' => $item->previewUrl(),
            ])->values(),
        ];
    }
}
