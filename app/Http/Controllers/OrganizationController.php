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

        $query = $user->isOperator()
            ? Organization::query()
            : $user->organizations();

        if ($name = OrganizationScope::nameFilter($request)) {
            $query->where('name', 'like', '%'.$name.'%');
        }

        return response()->json($query->orderBy('name')->get(['organizations.id', 'organizations.name']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'organization');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $organization = Organization::query()->create($data);

        return response()->json($organization, 201);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeSubject($request, 'organization');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $organization->update($data);

        return response()->json($organization);
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeSubject($request, 'organization');
        abort_if(
            $organization->screens()->exists() || $organization->mediaAssets()->exists() || $organization->playlists()->exists(),
            422,
            'Organization still has screens, media, or playlists.',
        );
        $organization->users()->detach();
        $organization->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeSubject(Request $request, string $subject): void
    {
        abort_unless($request->user()?->canManage($subject), 403);
    }
}
