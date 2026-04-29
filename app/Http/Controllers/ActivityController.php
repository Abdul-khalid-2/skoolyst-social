<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $workspaceId = $request->attributes->get('workspace_id');
        
        if (!$workspaceId && $request->hasSession()) {
            $workspaceId = $request->session()->get('current_workspace_id');
        }

        $activities = collect();
        
        if ($workspaceId) {
            $activities = Post::with(['postTargets.socialAccount'])
                ->where('workspace_id', $workspaceId)
                ->latest()
                ->limit(20)
                ->get();
        }

        return view('activity.index', compact('activities'));
    }
}
