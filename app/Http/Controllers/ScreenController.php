<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Screen;
use App\Support\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'screen');
        $organizationId = OrganizationScope::forTenant($request);

        $query = Screen::query()->where('organization_id', $organizationId);
        if ($name = OrganizationScope::nameFilter($request)) {
            $query->where('name', 'like', '%'.$name.'%');
        }

        return response()->json(
            $query->orderBy('name')->get()->map(fn (Screen $screen) => $screen->present())->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'screen');
        $organizationId = OrganizationScope::forTenant($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $screen = Screen::query()->create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'pairing_code' => Screen::makePairingCode(),
        ]);

        return response()->json($screen->present(), 201);
    }

    public function update(Request $request, Screen $screen): JsonResponse
    {
        $this->authorizeSubject($request, 'screen');
        $this->assertSameOrganization($request, $screen->organization_id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'playlist_id' => ['nullable', 'integer'],
        ]);

        if (array_key_exists('playlist_id', $data) && $data['playlist_id']) {
            abort_unless(
                Playlist::query()->whereKey($data['playlist_id'])->where('organization_id', $screen->organization_id)->exists(),
                422,
                'Playlist is not in this organization.',
            );
        }

        $screen->update([
            'name' => $data['name'],
            'playlist_id' => $data['playlist_id'] ?? null,
        ]);

        return response()->json($screen->fresh()->present());
    }

    public function destroy(Request $request, Screen $screen): JsonResponse
    {
        $this->authorizeSubject($request, 'screen');
        $this->assertSameOrganization($request, $screen->organization_id);
        $screen->delete();

        return response()->json(['ok' => true]);
    }

    public function regeneratePairingCode(Request $request, Screen $screen): JsonResponse
    {
        $this->authorizeSubject($request, 'screen');
        $this->assertSameOrganization($request, $screen->organization_id);
        $screen->update([
            'pairing_code' => Screen::makePairingCode(),
            'device_token' => null,
            'device_name' => null,
        ]);

        return response()->json($screen->fresh()->present());
    }

    private function assertSameOrganization(Request $request, int $organizationId): void
    {
        abort_unless(OrganizationScope::forTenant($request) === $organizationId, 404);
    }

    private function authorizeSubject(Request $request, string $subject): void
    {
        abort_unless($request->user()?->canManage($subject), 403);
    }
}
