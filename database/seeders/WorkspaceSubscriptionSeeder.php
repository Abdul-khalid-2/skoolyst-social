<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        Workspace::whereDoesntHave('subscriptions')->get()->each(function ($ws) {
            Subscription::create([
                'user_id'      => $ws->owner_id,
                'workspace_id' => $ws->id,
                'plan'         => $ws->plan ?? 'free',
                'status'       => 'active',
                'started_at'   => $ws->created_at ?? now(),
                'expires_at'   => null,
            ]);
        });

        $this->command->info('Subscriptions seeded for all workspaces.');
    }
}
