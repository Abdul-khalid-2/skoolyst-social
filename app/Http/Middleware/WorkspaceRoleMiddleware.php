<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceRoleMiddleware
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspaceId = $this->resolveWorkspaceId($request);

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($workspaceId);
        }
        $this->permissionRegistrar->setPermissionsTeamId($workspaceId);

        if ($workspaceId !== null) {
            $request->attributes->set('workspace_id', $workspaceId);
            if ($request->hasSession()) {
                $request->session()->put('current_workspace_id', $workspaceId);
            }
        }

        return $next($request);
    }

    private function resolveWorkspaceId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $workspace = $request->route('workspace');
        $candidate = null;

        if ($workspace instanceof Workspace) {
            $candidate = (int) $workspace->id;
        } elseif (is_numeric($workspace)) {
            $candidate = (int) $workspace;
        } elseif (is_numeric($request->header('X-Workspace-Id'))) {
            $candidate = (int) $request->header('X-Workspace-Id');
        } elseif (is_numeric($request->input('workspace_id'))) {
            $candidate = (int) $request->input('workspace_id');
        } elseif ($request->hasSession() && is_numeric($request->session()->get('current_workspace_id'))) {
            $candidate = (int) $request->session()->get('current_workspace_id');
        }

        if ($candidate !== null) {
            $isMember = $user->workspaces()
                ->where('workspaces.id', $candidate)
                ->wherePivot('is_active', true)
                ->exists();

            if (! $isMember) {
                abort(403, 'You are not a member of this workspace.');
            }

            return $candidate;
        }

        $fallback = $user->workspaces()
            ->wherePivot('is_active', true)
            ->select('workspaces.id')
            ->orderBy('workspaces.id')
            ->first();

        return $fallback?->id ? (int) $fallback->id : null;
    }
}
