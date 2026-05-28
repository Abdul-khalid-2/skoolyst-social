<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * UI-only controller for Social Posts analytics screens.
 * No API calls or data wiring — placeholder views only.
 */
class SocialPostsController extends Controller
{
    public function index(): View
    {
        return view('social-posts.index', [
            'title' => 'Social Posts',
            'description' => 'View and manage posts across your connected accounts',
        ]);
    }
}
