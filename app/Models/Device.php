<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'organization_id',
        'branch_id',
        'head_branch_id',
        'playlist_id',
        'media_asset_id',
        'screen_row',
        'name',
        'pairing_code',
        'device_token',
        'device_name',
        'last_seen_at',
        'reported_playing',
        'reported_item',
    ];

    protected $hidden = [
        'device_token',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'reported_playing' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function placeInBranch(?int $branchId, bool $isHead): void
    {
        if ($isHead && ! $branchId) {
            abort(422, 'A head must belong to a branch.');
        }
        if ($isHead && self::query()
            ->where('head_branch_id', $branchId)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->id))
            ->exists()) {
            abort(422, 'This branch already has a head.');
        }
        $this->branch_id = $branchId;
        $this->head_branch_id = $isHead ? $branchId : null;
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function claimCodes(): HasMany
    {
        return $this->hasMany(ClaimCode::class);
    }

    public function isLinked(): bool
    {
        if (array_key_exists('linked', $this->attributes)) {
            return (bool) $this->attributes['linked'];
        }

        return $this->claimCodes()->whereNotNull('claimed_at')->exists();
    }

    public static function makePairingCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::query()->where('pairing_code', $code)->exists());

        return $code;
    }

    public function present(): array
    {
        $this->loadMissing('playlist', 'branch', 'mediaAsset');

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'is_head' => $this->head_branch_id !== null,
            'name' => $this->name,
            'pairing_code' => $this->pairing_code,
            'device_name' => $this->device_name,
            'paired' => $this->device_token !== null,
            'linked' => $this->isLinked(),
            'last_seen_at' => $this->last_seen_at,
            'reported_playing' => (bool) $this->reported_playing,
            'reported_item' => $this->reported_item,
            'playlist_id' => $this->playlist_id,
            'playlist_name' => $this->playlist?->name,
            'media_asset_id' => $this->media_asset_id,
            'media_name' => $this->mediaAsset?->name,
            'screen_row' => $this->screen_row === null ? null : (int) $this->screen_row,
            'screen_name' => $this->screen_row === null ? null : 'Screen '.(((int) $this->screen_row) + 1),
            'playlist_live' => $this->playlist_id
                ? (bool) $this->playlist?->publications()->exists()
                : false,
        ];
    }
}
