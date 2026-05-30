<?php

namespace Tests\Unit;

use App\Services\SocialAccountProvisioner;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkedInPersonStatsTest extends TestCase
{
    public function test_fetch_linkedin_person_stats_uses_rest_analytics_and_posts(): void
    {
        config(['services.linkedin.rest_version' => '202504']);

        Http::fake([
            'api.linkedin.com/rest/memberFollowersCount*' => Http::response([
                'elements' => [['memberFollowersCount' => 42]],
            ]),
            'api.linkedin.com/rest/posts*' => Http::response([
                'paging' => ['total' => 7],
            ]),
        ]);

        $stats = SocialAccountProvisioner::fetchLinkedInPersonStats('test-token', 'U2tsfx2lpU');

        $this->assertSame(42, $stats['followers']);
        $this->assertSame(7, $stats['posts']);
        $this->assertNull($stats['following']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'memberFollowersCount')
                && ($request->data()['q'] ?? null) === 'me';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'rest/posts')
                && ($request->data()['q'] ?? null) === 'author'
                && ($request->data()['author'] ?? null) === 'urn:li:person:U2tsfx2lpU';
        });
    }

    public function test_linked_in_person_handle_for_storage_prefers_vanity(): void
    {
        $this->assertSame('abdul-khalid', SocialAccountProvisioner::linkedInPersonHandleForStorage('abdul-khalid', 'U2tsfx2lpU'));
        $this->assertSame('U2tsfx2lpU', SocialAccountProvisioner::linkedInPersonHandleForStorage(null, 'U2tsfx2lpU'));
    }
}
