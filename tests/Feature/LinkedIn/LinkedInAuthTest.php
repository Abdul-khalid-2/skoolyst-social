<?php

namespace Tests\Feature\LinkedIn;

use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkedInAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure LinkedIn platform exists (migration may have already inserted it)
        SocialPlatform::firstOrCreate(
            ['slug' => 'linkedin'],
            [
                'name' => 'LinkedIn',
                'icon' => 'linkedin',
                'color' => '#0A66C2',
                'is_active' => true,
                'supports_scheduling' => true,
                'supports_media' => true,
                'character_limit' => 3000,
                'connection_options' => json_encode([
                    'oauth_flow' => 'oauth2',
                    'supports_pages' => true,
                    'supports_personal' => true,
                ]),
            ]
        );
    }

    public function test_connect_linkedin_for_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $result = SocialAccountProvisioner::connectLinkedInForWorkspace(
            $workspace,
            '12345',
            'test_access_token',
            'test_refresh_token',
            now()->addDays(60),
            'John Doe',
            'https://example.com/avatar.jpg',
            'johndoe'
        );

        $account = $result['account'];
        $this->assertTrue($result['created']);
        $this->assertEquals($workspace->id, $account->workspace_id);
        $this->assertEquals('test_access_token', decrypt($account->access_token));
        $this->assertEquals('test_refresh_token', decrypt($account->refresh_token));
        $this->assertTrue($account->is_connected);

        $meta = $account->meta;
        $this->assertEquals('urn:li:person:12345', $meta['li_member_id']);
        $this->assertEquals('person', $meta['li_account_type']);
    }

    public function test_connect_linkedin_organization_for_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $result = SocialAccountProvisioner::connectLinkedInOrganizationForWorkspace(
            $workspace,
            '12345',
            '999888',
            'test_access_token',
            'test_refresh_token',
            now()->addDays(60),
            'My Company',
            'https://example.com/company-logo.jpg'
        );

        $account = $result['account'];
        $this->assertTrue($result['created']);
        $this->assertEquals('My Company', $account->account_name);
        $this->assertTrue($account->is_connected);

        $meta = $account->meta;
        $this->assertEquals('urn:li:organization:999888', $meta['li_member_id']);
        $this->assertEquals('organization', $meta['li_account_type']);
        $this->assertEquals('999888', $meta['li_organization_id']);
    }

    public function test_multiple_linkedin_accounts_in_same_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $personalResult = SocialAccountProvisioner::connectLinkedInForWorkspace(
            $workspace,
            '12345',
            'personal_token',
            'personal_refresh',
            now()->addDays(60),
            'John Doe'
        );

        $organizationResult = SocialAccountProvisioner::connectLinkedInOrganizationForWorkspace(
            $workspace,
            '12345',
            '999888',
            'org_token',
            'org_refresh',
            now()->addDays(60),
            'My Company'
        );

        $personal = $personalResult['account'];
        $organization = $organizationResult['account'];

        $accounts = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('platform', fn ($q) => $q->where('slug', 'linkedin'))
            ->get();

        $this->assertEquals(2, $accounts->count());
        $this->assertTrue($accounts->contains('id', $personal->id));
        $this->assertTrue($accounts->contains('id', $organization->id));
    }
}
