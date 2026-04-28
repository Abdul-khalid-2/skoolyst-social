<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkspaceSettingsService
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function getCurrentWorkspace(User $user): ?Workspace
    {
        $id = $this->dashboardService->resolveWorkspaceId($user);
        if ($id === null) {
            return null;
        }

        return Workspace::query()->find($id);
    }

    public function userCanEditWorkspaceName(User $user, Workspace $workspace): bool
    {
        return Gate::forUser($user)->allows('manageSettings', $workspace);
    }

    public function updateWorkspaceName(User $user, Workspace $workspace, string $name): void
    {
        if (! $this->userCanEditWorkspaceName($user, $workspace)) {
            abort(403, __('You cannot rename this workspace.'));
        }
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'workspace_name' => [__('A workspace name is required.')],
            ]);
        }
        $workspace->name = $name;
        $workspace->save();
    }
}
