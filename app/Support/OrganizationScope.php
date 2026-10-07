<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

class OrganizationScope
{
    public static function queries(Request $request): array
    {
        $raw = $request->input('queries', []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $item = json_decode($item, true);
            }
            if (is_array($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    public static function requestedId(Request $request): ?int
    {
        $fromBody = $request->input('organization_id');
        if ($fromBody !== null && $fromBody !== '') {
            return (int) $fromBody;
        }

        foreach (self::queries($request) as $query) {
            if (($query['field'] ?? null) === 'organization' && ($query['value'] ?? '') !== '') {
                return (int) $query['value'];
            }
        }

        return null;
    }

    public static function nameFilter(Request $request): ?string
    {
        foreach (self::queries($request) as $query) {
            if (($query['field'] ?? null) === 'name' && ($query['value'] ?? '') !== '') {
                return (string) $query['value'];
            }
        }

        $name = $request->input('name');

        return $name === null || $name === '' ? null : (string) $name;
    }

    public static function forTenant(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();
        $requested = self::requestedId($request);

        if ($user->isAdmin()) {
            abort_if($requested === null, 422, 'Select an organization.');
            abort_unless(Organization::query()->whereKey($requested)->exists(), 422, 'Select an organization.');

            return $requested;
        }

        $ids = $user->organizations()->pluck('organizations.id');
        if ($ids->count() === 1) {
            return (int) $ids->first();
        }

        abort_unless($requested !== null && $ids->contains($requested), 422, 'Select an organization.');

        return $requested;
    }
}
