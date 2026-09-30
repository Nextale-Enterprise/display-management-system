<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Publication;
use App\Models\PublicationItem;
use App\Support\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaylistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'playlist');
        $organizationId = OrganizationScope::forTenant($request);

        $query = Playlist::query()->where('organization_id', $organizationId)->with('items.mediaAsset');
        if ($name = OrganizationScope::nameFilter($request)) {
            $query->where('name', 'like', '%'.$name.'%');
        }

        return response()->json(
            $query->orderBy('name')->get()->map(fn (Playlist $playlist) => $playlist->present())->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'playlist');
        $organizationId = OrganizationScope::forTenant($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $playlist = Playlist::query()->create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
        ]);

        return response()->json($playlist->present(), 201);
    }

    public function update(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizeSubject($request, 'playlist');
        $this->assertSameOrganization($request, $playlist->organization_id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $playlist->update($data);

        return response()->json($playlist->fresh()->present());
    }

    public function syncItems(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizeSubject($request, 'playlist');
        $this->assertSameOrganization($request, $playlist->organization_id);
        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.media_asset_id' => ['required', 'integer'],
            'items.*.duration_ms' => ['required', 'integer', 'min:1000'],
        ]);

        $ids = collect($data['items'])->pluck('media_asset_id');
        $assets = MediaAsset::query()
            ->where('organization_id', $playlist->organization_id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        abort_unless($assets->count() === $ids->unique()->count(), 422, 'Media is not in this organization.');

        DB::transaction(function () use ($playlist, $data) {
            $playlist->items()->delete();
            foreach (array_values($data['items']) as $position => $item) {
                PlaylistItem::query()->create([
                    'playlist_id' => $playlist->id,
                    'media_asset_id' => $item['media_asset_id'],
                    'position' => $position,
                    'duration_ms' => $item['duration_ms'],
                ]);
            }
        });

        return response()->json($playlist->fresh()->present());
    }

    public function publish(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizeSubject($request, 'playlist');
        $this->assertSameOrganization($request, $playlist->organization_id);
        $playlist->load('items.mediaAsset');
        abort_if($playlist->items->isEmpty(), 422, 'Playlist has no items.');
        abort_if(
            $playlist->items->contains(fn (PlaylistItem $item) => $item->mediaAsset?->status !== 'ready'),
            422,
            'Every item must be ready before publish.',
        );

        $publication = DB::transaction(function () use ($playlist) {
            $generation = ((int) $playlist->publications()->max('generation')) + 1;
            $publication = Publication::query()->create([
                'organization_id' => $playlist->organization_id,
                'playlist_id' => $playlist->id,
                'generation' => $generation,
                'published_at' => now(),
            ]);
            foreach ($playlist->items as $item) {
                PublicationItem::query()->create([
                    'publication_id' => $publication->id,
                    'media_asset_id' => $item->media_asset_id,
                    'position' => $item->position,
                    'duration_ms' => $item->duration_ms,
                    'name' => $item->mediaAsset->name,
                    'type' => $item->mediaAsset->type,
                    'path' => $item->mediaAsset->path,
                ]);
            }

            return $publication;
        });

        return response()->json([
            'playlist' => $playlist->fresh()->present(),
            'generation' => $publication->generation,
        ]);
    }

    public function destroy(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizeSubject($request, 'playlist');
        $this->assertSameOrganization($request, $playlist->organization_id);
        $playlist->delete();

        return response()->json(['ok' => true]);
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
