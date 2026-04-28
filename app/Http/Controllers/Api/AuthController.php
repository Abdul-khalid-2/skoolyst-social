<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'workspace_name' => ['nullable', 'string', 'max:120'],
            'terms' => ['required', 'accepted'],
        ]);

        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->refresh();

            $workspaceName = (isset($data['workspace_name']) && trim($data['workspace_name']) !== '')
                ? trim($data['workspace_name'])
                : ($data['name']."'s Workspace");

            $workspace = Workspace::query()->create([
                'owner_id' => $user->id,
                'name' => $workspaceName,
                'slug' => $this->makeWorkspaceSlug($user->id, $workspaceName),
                'plan' => 'free',
            ]);

            $workspace->members()->attach($user->id, [
                'role' => 'owner',
                'is_active' => true,
            ]);
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId((int) $workspace->id);
            }
            app(PermissionRegistrar::class)->setPermissionsTeamId((int) $workspace->id);
            Role::findOrCreate('owner', 'web');
            $user->syncRoles(['owner']);

            $token = $user->createToken('auth')->plainTextToken;

            return response()->json([
                'user' => $this->userArray($user, request()),
                'token' => $token,
                'workspace' => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'plan' => $workspace->plan,
                ],
            ], 201);
        });
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('The provided credentials are incorrect.')],
            ]);
        }

        if ($user->is_active === false) {
            return response()->json([
                'message' => __('This account is disabled.'),
            ], 403);
        }

        $user->tokens()->where('name', 'auth')->delete();
        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => $this->userArray($user, $request),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json(['user' => $this->userArray($user, $request)]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $user->name = (string) $data['name'];
        $user->email = (string) $data['email'];
        if (array_key_exists('timezone', $data)) {
            $user->timezone = (string) ($data['timezone'] ?: 'UTC');
        }
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->userArray($user->fresh(), $request),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        if (! Hash::check((string) $data['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The current password is incorrect.')],
            ]);
        }

        $user->password = (string) $data['password'];
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userArray(User $user, ?Request $request = null): array
    {
        $permissionPayload = $this->permissionPayload($user, $request);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'timezone' => $user->timezone,
            'avatar' => $user->avatar,
            'is_active' => $user->is_active,
            'facebook_connected' => (bool) $user->facebook_id,
            'workspace_id' => $permissionPayload['workspace_id'],
            'roles' => $permissionPayload['roles'],
            'permissions' => $permissionPayload['permissions'],
        ];
    }

    /**
     * @return array{workspace_id: int|null, roles: array<int, string>, permissions: array<int, string>}
     */
    private function permissionPayload(User $user, ?Request $request = null): array
    {
        $workspaceId = $this->resolvePermissionWorkspaceId($user, $request);
        if ($workspaceId === null) {
            return [
                'workspace_id' => null,
                'roles' => [],
                'permissions' => [],
            ];
        }

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($workspaceId);
        }
        app(PermissionRegistrar::class)->setPermissionsTeamId($workspaceId);

        return [
            'workspace_id' => $workspaceId,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ];
    }

    private function resolvePermissionWorkspaceId(User $user, ?Request $request = null): ?int
    {
        $candidate = null;

        if ($request && is_numeric($request->route('workspace'))) {
            $candidate = (int) $request->route('workspace');
        } elseif ($request && $request->route('workspace') instanceof Workspace) {
            $candidate = (int) $request->route('workspace')->id;
        } elseif ($request && is_numeric($request->header('X-Workspace-Id'))) {
            $candidate = (int) $request->header('X-Workspace-Id');
        } elseif ($request && is_numeric($request->input('workspace_id'))) {
            $candidate = (int) $request->input('workspace_id');
        } elseif ($request && is_numeric($request->attributes->get('workspace_id'))) {
            $candidate = (int) $request->attributes->get('workspace_id');
        } elseif ($request && $request->hasSession() && is_numeric($request->session()->get('current_workspace_id'))) {
            $candidate = (int) $request->session()->get('current_workspace_id');
        }

        if ($candidate !== null) {
            $isMember = $user->workspaces()
                ->where('workspaces.id', $candidate)
                ->wherePivot('is_active', true)
                ->exists();
            if ($isMember) {
                return $candidate;
            }
        }

        $firstMembership = $user->workspaces()
            ->wherePivot('is_active', true)
            ->select('workspaces.id')
            ->orderBy('workspaces.id')
            ->first();

        return $firstMembership?->id ? (int) $firstMembership->id : null;
    }

    private function makeWorkspaceSlug(int $userId, string $workspaceName): string
    {
        $segment = Str::slug($workspaceName);
        if ($segment === '') {
            $segment = 'workspace';
        }

        return $segment.'-ws-'.$userId;
    }
}
