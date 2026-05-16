<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkspaceSwitchController extends Controller
{
    public function index(Request $request): View
    {
        $user      = $request->user();
        $currentId = (int) $request->session()->get('current_workspace_id', 0);

        $workspaces = $user->workspaces()
            ->wherePivot('is_active', true)
            ->withPivot('role')
            ->withCount('posts')
            ->with('socialAccounts')
            ->orderBy('workspaces.id')
            ->get()
            ->map(fn ($ws) => [
                'id'          => $ws->id,
                'name'        => $ws->name,
                'slug'        => $ws->slug,
                'logo'        => $ws->logo,
                'plan'        => $ws->plan,
                'role'        => $ws->pivot->role,
                'posts_count' => $ws->posts_count,
                'accounts'    => $ws->socialAccounts->where('is_connected', true)->count(),
                'is_current'  => $ws->id === $currentId,
            ]);

        return view('workspaces.switch', [
            'title'       => 'Switch Account',
            'description' => 'Switch between your workspaces.',
            'workspaces'  => $workspaces,
            'currentId'   => $currentId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        $name = trim($validated['name']);
        $user = $request->user();

        $workspace = Workspace::create([
            'owner_id'  => $user->id,
            'name'      => $name,
            'slug'      => Str::slug($name).'-ws-'.$user->id.'-'.time(),
            'plan'      => 'free',
            'is_active' => true,
        ]);

        $workspace->members()->attach($user->id, [
            'role'      => 'owner',
            'is_active' => true,
        ]);

        $request->session()->put('current_workspace_id', $workspace->id);

        return redirect()->route('workspace.switch')->with('success', 'Workspace "'.$name.'" created and activated.');
    }

    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        $isMember = $request->user()->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->wherePivot('is_active', true)
            ->exists();

        if (! $isMember) {
            abort(403);
        }

        $request->session()->put('current_workspace_id', $workspace->id);

        return redirect()->route('dashboard')->with('success', 'Switched to '.$workspace->name);
    }
}
