<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\PublicationItem;
use App\Models\Device;
use App\Support\PlaylistGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeviceController extends Controller
{
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pairing_code' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $device = Device::query()
            ->where('pairing_code', strtoupper($data['pairing_code']))
            ->first();
        abort_unless($device, 422, 'Pairing code is not valid.');

        $plain = bin2hex(random_bytes(32));
        $device->update([
            'device_token' => hash('sha256', $plain),
            'device_name' => $data['device_name'] ?? $device->device_name,
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'device_token' => $plain,
            'device_id' => $device->id,
            'organization_id' => $device->organization_id,
        ]);
    }

    public function manifest(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $device->load('playlist');

        $publication = null;
        if ($device->playlist_id) {
            $publication = Publication::query()
                ->where('playlist_id', $device->playlist_id)
                ->with('items.mediaAsset')
                ->orderByDesc('generation')
                ->first();
        }

        $items = $publication?->items ?? collect();
        $timeline = $device->screen_row === null ? [] : PlaylistGrid::timeline($items, (int) $device->screen_row);
        $byId = $items->keyBy('id');

        return response()->json([
            'device_id' => $device->id,
            'playlist_id' => $device->playlist_id,
            'screen_row' => $device->screen_row,
            'media_asset_id' => $device->media_asset_id,
            'generation' => $publication?->generation ?? 0,
            'items' => collect($timeline)->map(function (array $item) use ($byId) {
                $stored = $item['id'] ? $byId->get($item['id']) : null;

                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'duration_ms' => $item['duration_ms'],
                    'file_duration_ms' => $item['file_duration_ms'],
                    'url' => $stored instanceof PublicationItem ? $this->itemUrl($stored) : '',
                ];
            })->values(),
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $device->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function file(Request $request, PublicationItem $publicationItem)
    {
        abort_unless($request->hasValidSignature(), 403);

        return Storage::disk('media')->response($publicationItem->path, $publicationItem->name);
    }

    private function itemUrl(PublicationItem $item): string
    {
        return $item->previewUrl();
    }
}
