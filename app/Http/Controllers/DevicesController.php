<?php

namespace App\Http\Controllers;

use App\Models\ClaimCode;
use App\Models\Device;
use App\Models\Organization;
use App\Models\Playlist;
use App\Models\Publication;
use App\Support\OrganizationScope;
use App\Support\PlaylistGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DevicesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'device');
        $organizationId = OrganizationScope::forTenant($request);

        $query = Device::query()
            ->where('organization_id', $organizationId)
            ->withExists([
                'claimCodes as linked' => fn ($claim) => $claim->whereNotNull('claimed_at'),
            ]);
        if ($name = OrganizationScope::nameFilter($request)) {
            $query->where('name', 'like', '%'.$name.'%');
        }

        return response()->json(
            $query->orderBy('name')->get()->map(fn (Device $device) => $device->present())->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'device');
        $organizationId = OrganizationScope::forTenant($request);
        Organization::query()->findOrFail($organizationId)->assertCanAdd('device');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('organization_id', $organizationId)],
            'playlist_id' => ['required', 'integer'],
            'screen_row' => ['nullable', 'integer', 'min:0'],
            'is_head' => ['sometimes', 'boolean'],
        ]);
        $screenRow = array_key_exists('screen_row', $data) ? $data['screen_row'] : 0;
        [$playlistId, $screenRow] = $this->assignment($organizationId, $data['playlist_id'], $screenRow, true);

        $device = new Device([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'pairing_code' => Device::makePairingCode(),
            'playlist_id' => $playlistId,
            'screen_row' => $screenRow,
        ]);
        $device->placeInBranch($data['branch_id'], $request->boolean('is_head'));
        $device->save();

        return response()->json($device->present(), 201);
    }

    public function update(Request $request, Device $device): JsonResponse
    {
        $this->authorizeSubject($request, 'device');
        $this->assertSameOrganization($request, $device->organization_id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'playlist_id' => ['nullable', 'integer'],
            'screen_row' => ['nullable', 'integer', 'min:0'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('organization_id', $device->organization_id)],
            'is_head' => ['sometimes', 'boolean'],
        ]);

        $screenRow = array_key_exists('screen_row', $data) ? $data['screen_row'] : $device->screen_row;
        [$playlistId, $screenRow] = $this->assignment(
            $device->organization_id,
            $data['playlist_id'] ?? null,
            $screenRow,
            false,
        );

        $isHead = array_key_exists('is_head', $data)
            ? $request->boolean('is_head')
            : $device->head_branch_id !== null;
        $device->name = $data['name'];
        $device->playlist_id = $playlistId;
        $device->screen_row = $screenRow;
        $device->placeInBranch($data['branch_id'] ?? null, $isHead);
        $device->save();

        return response()->json($device->fresh()->present());
    }

    public function destroy(Request $request, Device $device): JsonResponse
    {
        $this->authorizeSubject($request, 'device');
        $this->assertSameOrganization($request, $device->organization_id);
        $device->delete();

        return response()->json(['ok' => true]);
    }

    public function release(Request $request, Device $device): JsonResponse
    {
        $this->authorizeSubject($request, 'device');
        $this->assertSameOrganization($request, $device->organization_id);
        $released = ClaimCode::query()
            ->where('device_id', $device->id)
            ->whereNotNull('claimed_at')
            ->update(['device_id' => null]);
        abort_if($released === 0, 422, 'This device is not linked to a TV.');

        return response()->json($device->fresh()->present());
    }

    public function regeneratePairingCode(Request $request, Device $device): JsonResponse
    {
        $this->authorizeSubject($request, 'device');
        $this->assertSameOrganization($request, $device->organization_id);
        $device->update([
            'pairing_code' => Device::makePairingCode(),
            'device_token' => null,
            'device_name' => null,
        ]);

        return response()->json($device->fresh()->present());
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function assignment(int $organizationId, ?int $playlistId, ?int $screenRow, bool $required): array
    {
        if (! $playlistId) {
            abort_if($required, 422, 'Publish the playlist before assigning it to a device.');

            return [null, null];
        }

        $playlist = Playlist::query()
            ->whereKey($playlistId)
            ->where('organization_id', $organizationId)
            ->first();
        abort_unless($playlist, 422, 'Playlist is not in this organization.');
        $publication = Publication::query()
            ->where('playlist_id', $playlist->id)
            ->orderByDesc('generation')
            ->first();
        abort_unless($publication, 422, 'Publish the playlist before assigning it to a device.');
        $rows = PlaylistGrid::rows($publication->items);
        abort_if($rows === [], 422, 'Publish the playlist before assigning it to a device.');
        if ($screenRow === null) {
            abort_if($required, 422, 'Pick a screen row.');

            return [$playlistId, null];
        }
        abort_unless(in_array($screenRow, $rows, true), 422, 'That screen is not in this playlist.');

        return [$playlistId, $screenRow];
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
