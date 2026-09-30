<?php

namespace App\Http\Controllers;

use App\Jobs\TranscodeVideo;
use App\Models\MediaAsset;
use App\Models\PlaylistItem;
use App\Models\PublicationItem;
use App\Support\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'media');
        $organizationId = OrganizationScope::forTenant($request);

        $query = MediaAsset::query()->where('organization_id', $organizationId);
        if ($name = OrganizationScope::nameFilter($request)) {
            $query->where('name', 'like', '%'.$name.'%');
        }

        return response()->json(
            $query->orderByDesc('id')->get()->map(fn (MediaAsset $asset) => $asset->present())->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'media');
        $organizationId = OrganizationScope::forTenant($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:204800'],
        ]);

        $extension = strtolower($data['file']->getClientOriginalExtension());
        $video = in_array($extension, ['mp4', 'webm', 'mov'], true);
        $image = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
        abort_unless($video || $image, 422, 'Unsupported file.');

        $path = $data['file']->store('org/'.$organizationId.'/media', 'media');
        $asset = MediaAsset::query()->create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'type' => $video ? 'video' : 'image',
            'path' => $path,
            'status' => $video ? 'processing' : 'ready',
            'duration_ms' => $video ? null : 10000,
        ]);

        if ($video) {
            TranscodeVideo::dispatch($asset->id);
        }

        return response()->json($asset->present(), 201);
    }

    public function destroy(Request $request, MediaAsset $mediaAsset): JsonResponse
    {
        $this->authorizeSubject($request, 'media');
        $this->assertSameOrganization($request, $mediaAsset->organization_id);
        abort_if(
            PlaylistItem::query()->where('media_asset_id', $mediaAsset->id)->exists()
            || PublicationItem::query()->where('media_asset_id', $mediaAsset->id)->exists(),
            422,
            'Asset is used by a playlist or a published snapshot.',
        );

        Storage::disk('media')->delete($mediaAsset->path);
        $mediaAsset->delete();

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
