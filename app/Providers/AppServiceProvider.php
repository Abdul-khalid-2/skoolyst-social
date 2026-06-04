<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Policies\AnalyticsPolicy;
use App\Policies\PostPolicy;
use App\Policies\SocialAccountPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\TwitterProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! file_exists(public_path('storage'))) {
            Log::warning('Storage symlink missing. Run: php artisan storage:link');
        }

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(SocialAccount::class, SocialAccountPolicy::class);

        Gate::define('analytics.view', [AnalyticsPolicy::class, 'view']);
        Gate::define('analytics.export', [AnalyticsPolicy::class, 'export']);

        Socialite::extend('twitteroauth2', function ($app) {
            $config = $app['config']['services.twitteroauth2'];

            return Socialite::buildProvider(TwitterProvider::class, $config);
        });
    }
}
