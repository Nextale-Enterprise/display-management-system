<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'user');

        $query = User::query()->with(['role', 'organizations']);
        if ($name = OrganizationScope::nameFilter($request)) {
            $query->where(function ($inner) use ($name) {
                $inner->where('name', 'like', '%'.$name.'%')
                    ->orWhere('email', 'like', '%'.$name.'%');
            });
        }
        if ($organizationId = OrganizationScope::requestedId($request)) {
            $query->whereHas('organizations', fn ($inner) => $inner->where('organizations.id', $organizationId));
        }

        return response()->json($query->orderBy('name')->get()->map(fn (User $user) => $this->present($user)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSubject($request, 'user');
        $data = $this->validated($request, true);
        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $this->emailFor($data['username']),
            'password' => $data['password'] ?? 'asd123',
            'role_id' => $this->roleId($data['role']),
        ]);
        $this->syncOrganizations($user, $data['role'], $data['organization_ids'] ?? []);

        return response()->json($this->present($user->fresh(['role', 'organizations'])), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeSubject($request, 'user');
        abort_if($user->isSuperadmin(), 422, 'A superadmin account stays above this form.');
        $data = $this->validated($request, false, $user);
        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $this->emailFor($data['username']),
            'role_id' => $this->roleId($data['role']),
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        $this->syncOrganizations($user, $data['role'], $data['organization_ids'] ?? []);

        return response()->json($this->present($user->fresh(['role', 'organizations'])));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeSubject($request, 'user');
        abort_if($user->isSuperadmin(), 422, 'A superadmin account stays above this form.');
        abort_if($request->user()->is($user), 422, 'You cannot delete yourself.');
        $user->organizations()->detach();
        $user->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, bool $creating, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(['admin', 'merchant'])],
            'organization_ids' => ['array'],
            'organization_ids.*' => ['integer', 'exists:organizations,id'],
        ]);
    }

    private function emailFor(string $username): string
    {
        return str_contains($username, '@') ? $username : $username.'@signage.test';
    }

    private function roleId(string $slug): int
    {
        return (int) Role::query()->where('slug', $slug)->value('id');
    }

    private function syncOrganizations(User $user, string $role, array $organizationIds): void
    {
        if ($role === 'admin') {
            $user->organizations()->sync([]);

            return;
        }

        abort_if($organizationIds === [], 422, 'A merchant needs an organization.');
        $user->organizations()->sync($organizationIds);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role?->slug,
            'organizations' => $user->organizations->map(fn ($organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
            ])->values(),
        ];
    }

    private function authorizeSubject(Request $request, string $subject): void
    {
        abort_unless($request->user()?->canManage($subject), 403);
    }
}
