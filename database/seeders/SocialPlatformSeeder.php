<?php

namespace Database\Seeders;

use App\Models\SocialPlatform;
use Illuminate\Database\Seeder;

class SocialPlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $platforms = [
            [
                'name' => 'Facebook',
                'slug' => 'facebook',
                'color' => '#1877F2',
                'character_limit' => null,
                'connection_options' => [
                    [
                        'key' => 'page',
                        'title' => 'Page',
                        'description' => 'Connect a Facebook Page for business publishing.',
                    ],
                    [
                        'key' => 'group',
                        'title' => 'Group',
                        'description' => 'Connect a Group flow if your app later supports group posting.',
                    ],
                ],
            ],
            [
                'name' => 'Instagram',
                'slug' => 'instagram',
                'color' => '#E4405F',
                'character_limit' => null,
                'connection_options' => [
                    [
                        'key' => 'professional',
                        'title' => 'Professional Account',
                        'description' => 'Use Instagram Business/Creator account connected to a Facebook Page.',
                    ],
                    [
                        'key' => 'personal',
                        'title' => 'Personal Account',
                        'description' => 'Personal account has limited API publishing capabilities.',
                    ],
                ],
            ],
            [
                'name' => 'Twitter',
                'slug' => 'twitter',
                'color' => '#1DA1F2',
                'character_limit' => 280,
                'connection_options' => [
                    [
                        'key' => 'x_standard',
                        'title' => 'Standard API',
                        'description' => 'Connect your X developer app and account.',
                    ],
                ],
            ],
            [
                'name' => 'LinkedIn',
                'slug' => 'linkedin',
                'color' => '#0A66C2',
                'character_limit' => null,
                'connection_options' => [
                    [
                        'key' => 'member',
                        'title' => 'Member Profile',
                        'description' => 'Publish on behalf of your LinkedIn member profile.',
                    ],
                    [
                        'key' => 'organization',
                        'title' => 'Organization Page',
                        'description' => 'Publish as a LinkedIn company page admin.',
                    ],
                ],
            ],
        ];

        foreach ($platforms as $platform) {
            SocialPlatform::query()->updateOrCreate(
                ['slug' => $platform['slug']],
                [
                    'name' => $platform['name'],
                    'color' => $platform['color'],
                    'character_limit' => $platform['character_limit'],
                    'is_active' => true,
                    'supports_scheduling' => true,
                    'supports_media' => true,
                    'icon' => null,
                    'connection_options' => $platform['connection_options'] ?? null,
                ]
            );
        }
    }
}
