<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'device_limit',
        'branch_limit',
        'subscription_starts_at',
        'subscription_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'subscription_starts_at' => 'date',
            'subscription_ends_at' => 'date',
        ];
    }

    public function assertCanAdd(string $kind): void
    {
        if ($this->subscription_starts_at && $this->subscription_starts_at->startOfDay()->isFuture()) {
            abort(422, 'This subscription period has not started.');
        }
        if ($this->subscription_ends_at && $this->subscription_ends_at->endOfDay()->isPast()) {
            abort(422, 'This subscription period has ended.');
        }
        if ($kind === 'device' && $this->device_limit !== null && $this->devices()->count() >= $this->device_limit) {
            abort(422, 'Device limit reached.');
        }
        if ($kind === 'branch' && $this->branch_limit !== null && $this->branches()->count() >= $this->branch_limit) {
            abort(422, 'Branch limit reached.');
        }
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'device_limit' => $this->device_limit,
            'branch_limit' => $this->branch_limit,
            'devices_count' => (int) ($this->devices_count ?? $this->devices()->count()),
            'branches_count' => (int) ($this->branches_count ?? $this->branches()->count()),
            'subscription_starts_at' => $this->subscription_starts_at?->toDateString(),
            'subscription_ends_at' => $this->subscription_ends_at?->toDateString(),
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }
}
