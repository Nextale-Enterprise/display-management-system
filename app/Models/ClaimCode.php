<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimCode extends Model
{
    protected $fillable = [
        'code',
        'role',
        'cms_id',
        'local_device_id',
        'organization_id',
        'device_id',
        'expires_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function claimed(): bool
    {
        return $this->claimed_at !== null;
    }

    public function expired(): bool
    {
        return ! $this->claimed() && $this->expires_at->isPast();
    }

    public function present(): array
    {
        return [
            'code' => $this->code,
            'role' => $this->role,
            'cms_id' => $this->cms_id,
            'local_device_id' => $this->local_device_id,
            'claimed' => $this->claimed(),
            'expired' => $this->expired(),
            'revoked' => $this->claimed() && $this->device_id === null,
            'device_id' => $this->device_id,
        ];
    }
}
