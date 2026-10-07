<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'organization');
        /** @var User $user */
        $user = $request->user();

        $query = $user->isAdmin()
            ? Organization::query()
            : $user->organizations();

        if ($name = OrganizationScope::nameFilter($request)) {
            $query->where('name', 'like', '%'.$name.'%');
        }

        return response()->json(
            $query->withCount(['devices', 'branches'])->orderBy('name')->get()->map(fn (Organization $organization) => $organization->present())->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'organization');
        $organization = Organization::query()->create($this->fields($request));

        return response()->json($organization->loadCount(['devices', 'branches'])->present(), 201);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeSubject($request, 'organization');
        $organization->update($this->fields($request, $organization));

        return response()->json($organization->fresh()->loadCount(['devices', 'branches'])->present());
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeSubject($request, 'organization');
        abort_if(
            $organization->devices()->exists() || $organization->mediaAssets()->exists() || $organization->playlists()->exists(),
            422,
            'Organization still has devices, media, or playlists.',
        );
        $organization->users()->detach();
        $organization->delete();

        return response()->json(['ok' => true]);
    }

    private function fields(Request $request, ?Organization $organization = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];
        if ($request->user()?->isSuperadmin()) {
            $rules['device_limit'] = ['nullable', 'integer', 'min:0'];
            $rules['branch_limit'] = ['nullable', 'integer', 'min:0'];
            $rules['subscription_starts_at'] = ['nullable', 'date'];
            $rules['subscription_ends_at'] = ['nullable', 'date'];
        }
        $data = $request->validate($rules);
        if ($request->user()?->isSuperadmin()
            && ! empty($data['subscription_starts_at'])
            && ! empty($data['subscription_ends_at'])
            && $data['subscription_ends_at'] < $data['subscription_starts_at']) {
            abort(422, 'The subscription end is before the start.');
        }
        if (! $request->user()?->isSuperadmin() && $organization) {
            return ['name' => $data['name']];
        }

        return $data;
    }

    private function authorizeSubject(Request $request, string $subject): void
    {
        abort_unless($request->user()?->canManage($subject), 403);
    }
}
