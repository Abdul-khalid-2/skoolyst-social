<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\PostListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostsController extends Controller
{
    public function __construct(
        private readonly PostListingService $postListing,
    ) {
    }

    public function index(Request $request): View
    {
        $raw = $request->query('status');
        $statusFilter = null;
        if (is_string($raw) && $raw !== '' && $raw !== 'all') {
            $statusFilter = $raw;
        }

        $data = $this->postListing->paginateForIndex($request->user(), $statusFilter, 20);

        return view('posts.index', array_merge($data, [
            'title' => 'Posts',
            'description' => 'View and manage your posts across connected platforms.',
        ]));
    }

    public function scheduled(Request $request): View
    {
        $data = $this->postListing->paginateScheduled($request->user(), 20);

        return view('posts.scheduled', array_merge($data, [
            'title' => 'Scheduled',
            'description' => 'Upcoming scheduled posts.',
        ]));
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->postListing->deleteForUser($request->user(), $post);

        return redirect()
            ->back()
            ->with('success', __('Post deleted.'));
    }
}
