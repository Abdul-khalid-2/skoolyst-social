<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Meta “User data deletion” (Facebook Login / Instagram):
    | - Data deletion callback URL: POST {APP_URL}/api/auth/facebook/data-deletion
    | - Public instructions/status page: GET {APP_URL}/privacy/data-deletion
    */
    'facebook' => [
        'client_id' => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/auth/facebook/callback'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v24.0'),
        'fetch_business_pages' => (bool) env('FACEBOOK_FETCH_BUSINESS_PAGES', true),
    ],

    /*
    | Public Media Mirror
    |
    | Instagram Graph API only accepts a publicly fetchable image_url / video_url
    | (no direct multipart upload like Facebook). Local dev domains (ngrok-free,
    | *.test, localhost, 127.0.0.1) are NOT reachable by Facebook's fetcher
    | because ngrok-free returns its browser warning interstitial. To bypass,
    | the InstagramPostService re-uploads the local file to a public anonymous
    | host before sending to Instagram.
    |
    | Supported drivers:
    |   - "catbox"     (free, no signup, https://catbox.moe — DEFAULT)
    |   - "0x0"        (free, no signup, https://0x0.st)
    |   - "imgbb"      (requires MEDIA_MIRROR_IMGBB_KEY)
    |   - "cloudinary" (requires CLOUDINARY_URL or _CLOUD_NAME/_API_KEY/_API_SECRET)
    |   - "none"       (disable mirroring; send original URL as-is)
    */
    'media_mirror' => [
        'driver' => env('MEDIA_MIRROR_DRIVER', 'catbox'),
        'imgbb_key' => env('MEDIA_MIRROR_IMGBB_KEY'),
        'cloudinary' => [
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => env('CLOUDINARY_API_KEY'),
            'api_secret' => env('CLOUDINARY_API_SECRET'),
            'url' => env('CLOUDINARY_URL'),
        ],
        'force_for_hosts' => array_values(array_filter(array_map('trim', explode(
            ',',
            (string) env('MEDIA_MIRROR_FORCE_HOSTS', 'ngrok-free.dev,ngrok-free.app,ngrok.app,ngrok.io,localhost,127.0.0.1,.test,.local')
        )))),
    ],

    /*
    | Facebook video upload + Instagram container polling can exceed PHP's default
    | max_execution_time (often 60s). Web requests use set_time_limit() before publishing.
    */
    'social_publish' => [
        'max_execution_seconds' => (int) env('SOCIAL_PUBLISH_MAX_EXECUTION', 600),
        'facebook_video_timeout' => (int) env('FACEBOOK_VIDEO_UPLOAD_TIMEOUT', 600),
    ],

];
