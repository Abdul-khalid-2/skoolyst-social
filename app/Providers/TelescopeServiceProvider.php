<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TelescopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
            return;
        }
    }

    public function boot(): void
    {
        if (!class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
            return;
        }

        $this->hideSensitiveRequestDetails();

        \Laravel\Telescope\Telescope::filter(function (\Laravel\Telescope\IncomingEntry $entry) {
            $isLocal = $this->app->environment('local');
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        \Laravel\Telescope\Telescope::hideRequestParameters(['_token']);

        \Laravel\Telescope\Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }
}