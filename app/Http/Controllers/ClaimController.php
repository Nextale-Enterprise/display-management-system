<?php

namespace App\Http\Controllers;

use App\Models\ClaimCode;
use App\Models\Device;
use App\Models\Publication;
use App\Models\PublicationItem;
use App\Support\OrganizationScope;
use App\Support\PlaylistGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClaimController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16'],
            'role' => ['required', 'in:head,screen'],
            'cms_id' => ['required', 'string', 'max:64'],
            'local_device_id' => ['nullable', 'integer'],
        ]);

        $code = strtoupper(trim($data['code']));
        $localId = $data['local_device_id'] ?? null;

        if ($data['role'] === 'screen' && ! $this->headClaimed($data['cms_id'])) {
            abort(422, 'Scan the head first.');
        }

        $existing = ClaimCode::query()->where('code', $code)->first();
        if ($existing?->claimed()) {
            abort(422, 'Code already used.');
        }

        $open = ClaimCode::query()
            ->where('cms_id', $data['cms_id'])
            ->where('role', $data['role'])
            ->whereNull('claimed_at')
            ->when(
                $localId === null,
                fn ($query) => $query->whereNull('local_device_id'),
                fn ($query) => $query->where('local_device_id', $localId),
            )
            ->where('code', '!=', $code);
        $open->update(['expires_at' => now()]);

        $claim = ClaimCode::query()->updateOrCreate(
            ['code' => $code],
            [
                'role' => $data['role'],
                'cms_id' => $data['cms_id'],
                'local_device_id' => $localId,
                'expires_at' => now()->addMinutes(10),
            ]
        );

        return response()->json($claim->fresh()->present(), 201);
    }

    public function show(string $code): JsonResponse
    {
        $claim = $this->find($code);
        abort_unless($claim, 404, 'Claim code was not found.');

        return response()->json($claim->present());
    }

    public function role(string $code): JsonResponse
    {
        $device = $this->playingDevice($code);
        $device->loadMissing('branch.head');
        $head = $device->branch?->head;

        return response()->json([
            'device_id' => $device->id,
            'branch_id' => $device->branch_id ?? 0,
            'is_head' => $device->head_branch_id !== null,
            'head_device_id' => $head?->id ?? 0,
            'head_device_name' => $head?->name ?? '',
        ]);
    }

    public function report(Request $request, string $code): JsonResponse
    {
        $headClaim = $this->find($code);
        abort_unless($headClaim, 404, 'Claim code was not found.');
        if ($headClaim->claimed() && $headClaim->device_id === null) {
            return response()->json([
                'devices' => [[
                    'code' => $headClaim->code,
                    'revoked' => true,
                    'is_head' => false,
                    'playback' => $this->emptyPlayback(),
                ]],
            ]);
        }

        $head = $this->playingDevice($code);
        abort_unless($head->head_branch_id !== null, 422, 'Only the branch head can report.');

        $data = $request->validate([
            'devices' => ['present', 'array'],
            'devices.*.code' => ['required', 'string', 'max:16'],
            'devices.*.playing' => ['required', 'boolean'],
            'devices.*.item' => ['nullable', 'string', 'max:255'],
            'devices.*.seen_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $devices = [];
        foreach ($data['devices'] as $row) {
            $claim = $this->find($row['code']);
            if (! $claim || ! $claim->claimed()) {
                continue;
            }
            if ($claim->device_id === null) {
                $devices[] = [
                    'code' => $claim->code,
                    'revoked' => true,
                    'is_head' => false,
                    'playback' => $this->emptyPlayback(),
                ];
                continue;
            }
            $device = $claim->device()->with('playlist')->first();
            if (! $device
                || $device->organization_id !== $head->organization_id
                || $device->branch_id !== $head->branch_id) {
                continue;
            }
            $device->update([
                'last_seen_at' => now(),
                'reported_playing' => (bool) $row['playing'],
                'reported_item' => $row['item'] ?: null,
            ]);
            $devices[] = [
                'code' => $claim->code,
                'revoked' => false,
                'is_head' => $device->head_branch_id !== null,
                'playback' => $this->playbackPayload($device->fresh()),
            ];
        }

        return response()->json(['devices' => $devices]);
    }

    public function playback(string $code): JsonResponse
    {
        return response()->json($this->playbackPayload($this->playingDevice($code)));
    }

    private function playbackPayload(Device $device): array
    {
        $device->loadMissing('playlist');
        $publication = null;
        if ($device->playlist_id) {
            $publication = Publication::query()
                ->where('playlist_id', $device->playlist_id)
                ->with('items.mediaAsset')
                ->orderByDesc('generation')
                ->first();
        }
        $items = $publication?->items ?? collect();
        $timeline = $device->screen_row === null
            ? []
            : PlaylistGrid::timeline($items, (int) $device->screen_row);
        $playable = array_values(array_filter(
            $timeline,
            fn (array $item) => in_array($item['type'], ['video', 'image'], true),
        ));
        $pin = count($playable) === 1 ? (int) $playable[0]['media_asset_id'] : 0;

        return [
            'assigned' => $timeline !== [],
            'playlist_id' => $timeline !== [] ? $device->playlist_id : 0,
            'playlist_name' => $timeline !== [] ? $device->playlist?->name : '',
            'screen_row' => (int) ($device->screen_row ?? 0),
            'media_asset_id' => $pin,
            'generation' => $timeline !== [] ? $publication->generation : 0,
            'items' => $timeline === [] ? [] : PlaylistGrid::library($items),
            'row_items' => $timeline,
            'wall' => $timeline === [] ? [] : PlaylistGrid::wall($items),
        ];
    }

    private function emptyPlayback(): array
    {
        return [
            'assigned' => false,
            'playlist_id' => 0,
            'playlist_name' => '',
            'screen_row' => 0,
            'media_asset_id' => 0,
            'generation' => 0,
            'items' => [],
            'row_items' => [],
        ];
    }

    public function playbackFile(string $code, PublicationItem $publicationItem)
    {
        $device = $this->playingDevice($code);
        $publication = Publication::query()
            ->where('playlist_id', $device->playlist_id)
            ->orderByDesc('generation')
            ->first();
        abort_unless($publication && $publicationItem->publication_id === $publication->id, 404);
        abort_unless(Storage::disk('media')->exists($publicationItem->path), 404);

        return Storage::disk('media')->response($publicationItem->path, $publicationItem->name);
    }

    private function playingDevice(string $code): Device
    {
        $claim = $this->find($code);
        abort_unless($claim && $claim->claimed() && $claim->device_id, 404, 'Claim code was not found.');
        $device = $claim->device()->with('playlist')->first();
        abort_unless($device, 404, 'Claim code was not found.');

        return $device;
    }

    public function accept(Request $request, string $code): JsonResponse
    {
        abort_unless($request->user()?->canManage('device'), 403);
        $organizationId = OrganizationScope::forTenant($request);
        $data = $request->validate([
            'device_id' => ['required', 'integer'],
        ]);

        $result = DB::transaction(function () use ($data, $organizationId, $code) {
            $device = Device::query()->whereKey($data['device_id'])->lockForUpdate()->first();
            abort_unless($device && $device->organization_id === $organizationId, 422, 'Device is not in this organization.');
            abort_if(
                ClaimCode::query()->where('device_id', $device->id)->whereNotNull('claimed_at')->lockForUpdate()->exists(),
                422,
                'This device is already linked to a TV.',
            );
            $claim = ClaimCode::query()->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
            abort_unless($claim, 404, 'Claim code was not found.');
            abort_if($claim->claimed(), 422, 'Code already used.');
            abort_if($claim->expired(), 422, 'Code expired.');
            if ($claim->role === 'screen' && ! $this->headClaimed($claim->cms_id)) {
                abort(422, 'Scan the head first.');
            }

            $claim->update([
                'organization_id' => $organizationId,
                'device_id' => $device->id,
                'claimed_at' => now(),
            ]);

            return [$claim->fresh(), $device->fresh()];
        });

        return response()->json([
            'claim' => $result[0]->present(),
            'device' => $result[1]->present(),
        ]);
    }

    private function find(string $code): ?ClaimCode
    {
        return ClaimCode::query()->where('code', strtoupper(trim($code)))->first();
    }

    private function headClaimed(string $cmsId): bool
    {
        return ClaimCode::query()
            ->where('cms_id', $cmsId)
            ->where('role', 'head')
            ->whereNotNull('claimed_at')
            ->whereNotNull('device_id')
            ->exists();
    }
}
