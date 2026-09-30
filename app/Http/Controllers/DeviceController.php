<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\PublicationItem;
use App\Models\Screen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class DeviceController extends Controller
{
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pairing_code' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $screen = Screen::query()
            ->where('pairing_code', strtoupper($data['pairing_code']))
            ->first();
        abort_unless($screen, 422, 'Pairing code is not valid.');

        $plain = bin2hex(random_bytes(32));
        $screen->update([
            'device_token' => hash('sha256', $plain),
            'device_name' => $data['device_name'] ?? $screen->device_name,
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'device_token' => $plain,
            'screen_id' => $screen->id,
            'organization_id' => $screen->organization_id,
        ]);
    }

    public function manifest(Request $request): JsonResponse
    {
        /** @var Screen $screen */
        $screen = $request->attributes->get('screen');
        $screen->load('playlist');

        $publication = null;
        if ($screen->playlist_id) {
            $publication = Publication::query()
                ->where('playlist_id', $screen->playlist_id)
                ->with('items')
                ->orderByDesc('generation')
                ->first();
        }

        return response()->json([
            'screen_id' => $screen->id,
            'playlist_id' => $screen->playlist_id,
            'generation' => $publication?->generation ?? 0,
            'items' => $publication
                ? $publication->items->map(fn (PublicationItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'duration_ms' => $item->duration_ms,
                    'url' => $this->itemUrl($item),
                ])->values()
                : [],
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var Screen $screen */
        $screen = $request->attributes->get('screen');
        $screen->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function file(Request $request, PublicationItem $publicationItem)
    {
        abort_unless($request->hasValidSignature(), 403);

        return Storage::disk('media')->response($publicationItem->path, $publicationItem->name);
    }

    private function itemUrl(PublicationItem $item): string
    {
        if (config('filesystems.disks.media.driver') === 's3') {
            return Storage::disk('media')->temporaryUrl($item->path, now()->addHours(12));
        }

        return URL::temporarySignedRoute('media.file', now()->addHours(12), [
            'publicationItem' => $item->id,
        ]);
    }
}
