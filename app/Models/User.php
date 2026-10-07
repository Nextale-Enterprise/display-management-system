<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->role?->slug === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role?->slug, ['admin', 'superadmin'], true);
    }

    public function canManage(string $subject): bool
    {
        return in_array($subject, $this->abilitySubjects(), true);
    }

    public function abilitySubjects(): array
    {
        if ($this->isSuperadmin()) {
            return ['organization', 'user', 'device', 'playlist'];
        }

        if ($this->isAdmin()) {
            return ['organization', 'device', 'playlist'];
        }

        return ['device', 'playlist'];
    }

    public function panels(): array
    {
        return $this->isAdmin() ? ['system', 'organization'] : ['organization'];
    }

    public function present(): array
    {
        $this->loadMissing('role', 'organizations');
        $organizations = $this->isAdmin()
            ? Organization::query()->withCount(['devices', 'branches'])->orderBy('name')->get()
            : $this->organizations()->withCount(['devices', 'branches'])->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role?->slug,
            'organizations' => $organizations->map(fn (Organization $organization) => $organization->present())->values(),
            'abilities' => collect($this->abilitySubjects())->map(fn (string $subject) => [
                'action' => 'manage',
                'subject' => $subject,
            ])->values(),
            'panels' => $this->panels(),
        ];
    }
}
