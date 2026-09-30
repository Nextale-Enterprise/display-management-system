<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Screen extends Model
{
    protected $fillable = [
        'organization_id',
        'playlist_id',
        'name',
        'pairing_code',
        'device_token',
        'device_name',
        'last_seen_at',
    ];

    protected $hidden = [
        'device_token',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
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
        $this->loadMissing('playlist');

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'pairing_code' => $this->pairing_code,
            'device_name' => $this->device_name,
            'paired' => $this->device_token !== null,
            'last_seen_at' => $this->last_seen_at,
            'playlist_id' => $this->playlist_id,
            'playlist_name' => $this->playlist?->name,
        ];
    }
}
