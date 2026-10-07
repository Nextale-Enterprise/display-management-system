<?php

namespace App\Http\Controllers;

use App\Jobs\TranscodeVideo;
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

    public function addItem(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizeSubject($request, 'playlist');
        $this->assertSameOrganization($request, $playlist->organization_id);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:204800'],
        ]);

        $extension = strtolower($data['file']->getClientOriginalExtension());
        $video = in_array($extension, ['mp4', 'webm', 'mov'], true);
        $image = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
        abort_unless($video || $image, 422, 'Unsupported file.');

        $name = pathinfo($data['file']->getClientOriginalName(), PATHINFO_FILENAME);
        $path = $data['file']->store('org/'.$playlist->organization_id.'/media', 'media');
        $asset = MediaAsset::query()->create([
            'organization_id' => $playlist->organization_id,
            'name' => $name !== '' ? $name : 'Media',
            'type' => $video ? 'video' : 'image',
            'path' => $path,
            'status' => $video ? 'processing' : 'ready',
            'duration_ms' => $video ? null : 10000,
        ]);
        if ($video) {
            TranscodeVideo::dispatch($asset->id);
        }

        $row = $request->exists('row_index')
            ? max(0, (int) $request->input('row_index'))
            : ((int) ($playlist->items()->max('row_index') ?? -1)) + 1;
        $column = $request->exists('column_index') ? max(0, (int) $request->input('column_index')) : 0;
        $duration = (int) $request->input('duration_ms', 0);
        if ($duration < 1000) {
            $duration = (int) ($playlist->items()->where('column_index', $column)->max('duration_ms') ?: ($asset->duration_ms ?? 10000));
        }
        $playlist->items()->where('row_index', $row)->where('column_index', $column)->delete();
        PlaylistItem::query()->create([
            'playlist_id' => $playlist->id,
            'media_asset_id' => $asset->id,
            'position' => ($row * 1000) + $column,
            'row_index' => $row,
            'column_index' => $column,
            'duration_ms' => $duration,
        ]);

        return response()->json($playlist->fresh()->present(), 201);
    }

    public function syncItems(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizeSubject($request, 'playlist');
        $this->assertSameOrganization($request, $playlist->organization_id);
        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.media_asset_id' => ['required', 'integer'],
            'items.*.row_index' => ['nullable', 'integer', 'min:0'],
            'items.*.column_index' => ['nullable', 'integer', 'min:0'],
            'items.*.duration_ms' => ['required', 'integer', 'min:1000'],
        ]);

        $ids = collect($data['items'])->pluck('media_asset_id');
        $assets = MediaAsset::query()
            ->where('organization_id', $playlist->organization_id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        abort_unless($assets->count() === $ids->unique()->count(), 422, 'Media is not in this organization.');

        $placed = [];
        foreach (array_values($data['items']) as $position => $item) {
            $row = array_key_exists('row_index', $item) && $item['row_index'] !== null ? (int) $item['row_index'] : $position;
            $column = array_key_exists('column_index', $item) && $item['column_index'] !== null ? (int) $item['column_index'] : 0;
            $key = $row.':'.$column;
            abort_if(isset($placed[$key]), 422, 'Two files cannot share one screen cell.');
            $placed[$key] = [
                'media_asset_id' => $item['media_asset_id'],
                'row_index' => $row,
                'column_index' => $column,
                'duration_ms' => $item['duration_ms'],
                'position' => ($row * 1000) + $column,
            ];
        }

        DB::transaction(function () use ($playlist, $placed) {
            $playlist->items()->delete();
            foreach ($placed as $item) {
                PlaylistItem::query()->create([
                    'playlist_id' => $playlist->id,
                    'media_asset_id' => $item['media_asset_id'],
                    'position' => $item['position'],
                    'row_index' => $item['row_index'],
                    'column_index' => $item['column_index'],
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
                    'row_index' => $item->row_index,
                    'column_index' => $item->column_index,
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
