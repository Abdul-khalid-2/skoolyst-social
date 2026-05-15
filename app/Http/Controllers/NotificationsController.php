<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Services\PostListingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NotificationsController extends Controller
{
    public function __construct(private readonly PostListingService $postListing) {}

    public function index(Request $request): View
    {
        $workspace     = $this->postListing->resolveWorkspaceForUser($request->user());
        $notifications = collect();

        if ($workspace) {
            $wid = (int) $workspace->id;

            // 1. Failed post targets (publish errors)
            PostTarget::where('status', 'failed')
                ->whereHas('post', fn ($q) => $q->where('workspace_id', $wid))
                ->with(['post', 'socialPlatform'])
                ->latest('updated_at')
                ->limit(20)
                ->get()
                ->each(function ($target) use (&$notifications) {
                    $notifications->push([
                        'type'         => 'error',
                        'icon'         => 'x-circle',
                        'title'        => 'Post failed on '.ucfirst($target->socialPlatform?->slug ?? 'platform'),
                        'body'         => $target->error_message ?? 'An error occurred while publishing.',
                        'time'         => $target->updated_at,
                        'action_label' => 'View Post',
                        'action_url'   => url('/posts'),
                    ]);
                });

            // 2. Successfully published posts (last 7 days)
            PostTarget::where('status', 'published')
                ->whereHas('post', fn ($q) => $q->where('workspace_id', $wid))
                ->where('published_at', '>=', now()->subDays(7))
                ->with(['post', 'socialPlatform'])
                ->latest('published_at')
                ->limit(15)
                ->get()
                ->each(function ($target) use (&$notifications) {
                    $notifications->push([
                        'type'         => 'success',
                        'icon'         => 'check-circle',
                        'title'        => 'Post published to '.ucfirst($target->socialPlatform?->slug ?? 'platform'),
                        'body'         => Str::limit($target->post?->caption ?? '', 100),
                        'time'         => $target->published_at,
                        'action_label' => 'View Posts',
                        'action_url'   => url('/posts'),
                    ]);
                });

            // 3. Expiring / expired social account tokens
            SocialAccount::where('workspace_id', $wid)
                ->where('is_connected', true)
                ->whereNotNull('token_expires_at')
                ->with('platform')
                ->get()
                ->each(function ($account) use (&$notifications) {
                    $exp         = Carbon::parse($account->token_expires_at);
                    $platformSlug = ucfirst($account->platform?->slug ?? 'Account');
                    $accountName  = $account->account_name ?? $account->account_handle ?? 'account';
                    if ($exp->isPast()) {
                        $notifications->push([
                            'type'         => 'error',
                            'icon'         => 'key',
                            'title'        => $platformSlug.' token expired',
                            'body'         => 'Re-connect your '.$accountName.' to continue posting.',
                            'time'         => $exp,
                            'action_label' => 'Go to Accounts',
                            'action_url'   => url('/accounts'),
                        ]);
                    } elseif ($exp->diffInDays(now()) <= 7) {
                        $notifications->push([
                            'type'         => 'warning',
                            'icon'         => 'clock',
                            'title'        => $platformSlug.' token expiring soon',
                            'body'         => 'Token expires '.$exp->diffForHumans().'. Re-connect to avoid interruptions.',
                            'time'         => now(),
                            'action_label' => 'Go to Accounts',
                            'action_url'   => url('/accounts'),
                        ]);
                    }
                });

            // 4. Upcoming scheduled posts (next 24 hours)
            Post::where('workspace_id', $wid)
                ->where('status', 'scheduled')
                ->whereBetween('scheduled_at', [now(), now()->addHours(24)])
                ->with('postTargets.socialPlatform')
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get()
                ->each(function ($post) use (&$notifications) {
                    $notifications->push([
                        'type'         => 'info',
                        'icon'         => 'calendar',
                        'title'        => 'Post going live '.Carbon::parse($post->scheduled_at)->diffForHumans(),
                        'body'         => Str::limit($post->caption ?? '', 100),
                        'time'         => $post->scheduled_at,
                        'action_label' => 'View Scheduled',
                        'action_url'   => url('/scheduled'),
                    ]);
                });
        }

        $notifications = $notifications->sortByDesc('time')->values();

        return view('notifications.index', [
            'title'         => 'Notifications',
            'description'   => 'Your recent activity and alerts.',
            'notifications' => $notifications,
            'workspace'     => $workspace,
        ]);
    }
}
