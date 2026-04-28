<?php

namespace Database\Seeders;

use App\Models\Workspace;
use App\Services\SocialAccountProvisioner;
use Illuminate\Database\Seeder;

class WorkspaceSocialAccountsSeeder extends Seeder
{
    /**
     * Ensure placeholder social_accounts for every workspace (idempotent; same as registration + publishing-accounts).
     */
    public function run(): void
    {
        foreach (Workspace::query()->get() as $workspace) {
            SocialAccountProvisioner::ensureForWorkspace($workspace);
        }
    }
}
