# Cursor Prompt: LinkedIn Social Automation Integration
# Skoolyst Social — Laravel Project

---

## CONTEXT

You are working on **Skoolyst Social**, a Laravel 11 / PHP 8.2+ multi-workspace social media scheduling and publishing platform. The project follows strict architectural conventions defined in `laravel-project-rules.md`. You must read and honour all rules in that file before writing a single line.

The app currently supports **Facebook** and **Instagram** (via Meta Graph API). Your task is to integrate **LinkedIn** as a third publishing platform using the same patterns already established in the codebase.

---

## DATABASE STRUCTURE (existing — read-only, do not alter these)

```
social_platforms        id, name, slug, icon, color, is_active, supports_scheduling,
                        supports_media, character_limit, connection_options, timestamps

social_accounts         id, workspace_id, social_platform_id, platform_page_id,
                        platform_user_id, account_name, account_handle, avatar,
                        access_token (encrypted), refresh_token (encrypted, nullable),
                        token_expires_at, scopes (json), followers_count, fan_count,
                        following_count, posts_count, is_connected, meta (json), timestamps

posts                   id, workspace_id, user_id, caption, content, image_url,
                        link_url, platforms (json), status (enum), scheduled_at,
                        published_at, timezone, ai_generated, fb_post_id, ig_post_id,
                        fb_error, ig_error, timestamps, soft_deletes

post_targets            id, post_id, social_account_id, social_platform_id,
                        status (enum: pending|publishing|published|failed|skipped),
                        platform_post_id, published_at, error_message, timestamps

post_media              id, post_id, media_asset_id, url, type, size, mime_type,
                        sort_order, timestamps

publish_jobs            id, post_target_id, job_id, status, attempts,
                        scheduled_at, started_at, completed_at, timestamps

publish_logs            id, publish_job_id, level (info|warning|error), message,
                        response (json), http_status, timestamps

workspaces              id, owner_id, name, slug, logo, industry, plan, is_active, timestamps
workspace_user          id, workspace_id, user_id, role (owner|admin|editor|viewer),
                        is_active, timestamps
```

---

## EXISTING PATTERNS TO FOLLOW EXACTLY

### OAuth / Connection pattern (use FacebookAuthController as reference)
- `app/Http/Controllers/Api/FacebookAuthController.php` → redirect + callback via Laravel Socialite
- `app/Services/SocialAccountProvisioner.php` → provisions/upserts `SocialAccount` rows after OAuth
- Tokens are **always** stored with `encrypt()` and decrypted with `decrypt()` inside services
- `SocialAccount::$fillable` already includes `refresh_token` — use it for LinkedIn's refresh token

### Publishing pattern (use InstagramPostService / SocialPostService as reference)
- `app/Services/SocialPostService.php` → platform-specific publish methods, returns `['success' => bool, 'post_id' => string]` or `['success' => false, 'error' => string]`
- `app/Services/InstagramPostService.php` → more advanced two-step publish flow (container → publish)
- `app/Http/Controllers/Api/PostController.php` → orchestrates publishing; calls service per platform slug
- `app/Console/Commands/PublishScheduledPosts.php` → scheduler picks up `post_targets` with status=pending

### Provisioner pattern
- `SocialAccountProvisioner` has static methods: `connectFacebookPagesForWorkspace`, `connectFacebookOnlyForWorkspace`
- Add equivalent: `connectLinkedInForWorkspace` and `connectLinkedInOrganizationsForWorkspace`

### API Resource pattern
- All API responses use `app/Http/Resources/` classes (create if missing for LinkedIn)
- Controllers stay thin — delegate everything to services and actions

### Permission pattern (Spatie)
- Policies in `app/Policies/SocialAccountPolicy.php` — no changes needed
- Workspace roles: owner, admin, editor, viewer (defined in `WorkspacePermissionMap`)

---

## TASK: IMPLEMENT LINKEDIN INTEGRATION

### Step 1 — Migration: Add LinkedIn platform seed data

Create migration: `2026_XX_XX_000000_seed_linkedin_social_platform.php`

Insert into `social_platforms`:
```php
[
    'name'                 => 'LinkedIn',
    'slug'                 => 'linkedin',
    'icon'                 => 'linkedin',
    'color'                => '#0A66C2',
    'is_active'            => true,
    'supports_scheduling'  => true,
    'supports_media'       => true,
    'character_limit'      => 3000,
    'connection_options'   => json_encode([
        'oauth_flow'          => 'oauth2',
        'supports_pages'      => true,   // LinkedIn Organization Pages
        'supports_personal'   => true,   // Personal profiles
    ]),
]
```

### Step 2 — Migration: Add LinkedIn columns to posts table

Create migration: `2026_XX_XX_000001_add_linkedin_columns_to_posts_table.php`

Add to `posts` table:
- `li_post_id` — `string`, nullable, after `ig_post_id`
- `li_error` — `text`, nullable, after `ig_error`

Also modify the `status` enum to include `'partial'` if not already present (it was added in `2026_04_24_100000`).

### Step 3 — Migration: Add LinkedIn token columns to social_accounts (if needed)

The existing `social_accounts` table already has `refresh_token`. LinkedIn uses OAuth 2.0 with a long-lived access token (60 days) and a refresh token (1 year). No new columns needed — use existing ones.

Add to `meta` JSON (no migration needed — already a JSON column):
- `li_member_id` — LinkedIn URN (urn:li:person:xxx or urn:li:organization:xxx)
- `li_account_type` — `'person'` or `'organization'`
- `li_vanity_name` — LinkedIn vanity URL slug

### Step 4 — LinkedIn OAuth Controller

Create: `app/Http/Controllers/Api/LinkedInAuthController.php`

Follow the exact same structure as `FacebookAuthController`. Use **Laravel Socialite** with the `linkedin-openid` driver (package: `socialiteproviders/linkedin-openid`) OR the built-in `linkedin` driver if available.

Required scopes for posting:
```php
['openid', 'profile', 'email', 'w_member_social', 'r_basicprofile']
```

For Organization Pages (additional OAuth or API call after token):
- After getting user token, call LinkedIn API to fetch organizations the user admins
- Store each org as a separate `SocialAccount` row with `platform_page_id` = organization URN

Controller methods:
```php
public function redirectToLinkedIn(): RedirectResponse
public function handleCallback(Request $request): RedirectResponse
```

On successful callback:
1. Get the LinkedIn user via Socialite
2. Exchange for long-lived token (LinkedIn tokens are already long-lived, 60 days)
3. Call `SocialAccountProvisioner::connectLinkedInForWorkspace()`
4. Redirect to frontend with `?connected=linkedin` on success or `?error=code` on failure

### Step 5 — LinkedIn Account Provisioner

Add to `app/Services/SocialAccountProvisioner.php`:

```php
public static function connectLinkedInForWorkspace(
    Workspace $workspace,
    string $linkedInUrn,       // urn:li:person:xxxxx
    string $accessToken,
    string $refreshToken,
    Carbon $expiresAt,
    string $displayName,
    ?string $avatarUrl,
    ?string $vanityName,
    int $followersCount = 0,
): SocialAccount

public static function connectLinkedInOrganizationsForWorkspace(
    Workspace $workspace,
    string $userAccessToken,
    string $userUrn,
    Carbon $expiresAt,
): int  // returns count of orgs connected
```

For organizations, call LinkedIn API:
```
GET https://api.linkedin.com/v2/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&projection=(elements*(organization~(id,localizedName,logoV2(original~:playbackUrl),followingInfo)))
Authorization: Bearer {accessToken}
```

Each organization becomes a separate `SocialAccount` row:
- `platform_page_id` = `urn:li:organization:{id}`
- `platform_user_id` = user URN
- `account_name` = org name
- `meta->li_account_type` = `'organization'`

### Step 6 — LinkedIn Post Service

Create: `app/Services/LinkedInPostService.php`

Base URL: `https://api.linkedin.com/v2/`
API version header: `LinkedIn-Version: 202406` (use latest stable)

Methods:

```php
public function publishTextPost(
    SocialAccount $account,
    string $text,
    ?string $linkUrl = null,
): array  // ['success' => bool, 'post_id' => string] or ['success' => false, 'error' => string]

public function publishImagePost(
    SocialAccount $account,
    string $text,
    string $imageUrl,    // local storage path resolved from URL, same as Facebook pattern
): array

public function publishVideoPost(
    SocialAccount $account,
    string $text,
    string $videoUrl,
): array
```

LinkedIn UGC Posts API flow (text/image):
1. Resolve author URN from `$account->platform_page_id` (org) or `$account->platform_user_id` (person)
2. For images: upload image to LinkedIn first via Assets API (`POST /assets?action=registerUpload`), then publish
3. POST to `https://api.linkedin.com/v2/ugcPosts` with body:
```json
{
  "author": "urn:li:person:xxx",
  "lifecycleState": "PUBLISHED",
  "specificContent": {
    "com.linkedin.ugc.ShareContent": {
      "shareCommentary": { "text": "your caption" },
      "shareMediaCategory": "NONE"
    }
  },
  "visibility": { "com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC" }
}
```
4. Return `['success' => true, 'post_id' => $responseId]`

Token resolution must use same pattern as `SocialPostService::resolveToken()` — `decrypt()` with fallback.

**Error handling**: Catch `GuzzleException`, log via `Log::error('LinkedIn publish failed', [...])`, return `['success' => false, 'error' => $message]`.

### Step 7 — Wire LinkedIn into PostController

In `app/Http/Controllers/Api/PostController.php`:

1. Inject `LinkedInPostService` in the constructor alongside existing services
2. In the platform publishing loop (where `facebook` and `instagram` slugs are handled), add a `linkedin` case:
```php
'linkedin' => $this->linkedInPostService->publishTextPost($account, $caption, $linkUrl),
```
3. Store result in `li_post_id` / `li_error` on the post record (same pattern as `fb_post_id` / `fb_error`)
4. Update the corresponding `PostTarget` status to `published` or `failed`

### Step 8 — Wire LinkedIn into PublishScheduledPosts Command

In `app/Console/Commands/PublishScheduledPosts.php`:

The command already loops over `post_targets` joined with `social_accounts` and `social_platforms`. Add a `linkedin` case in the platform dispatch switch/if block, calling `LinkedInPostService`.

### Step 9 — Routes

Add to `routes/api.php` (or wherever Facebook auth routes are defined):

```php
Route::prefix('auth/linkedin')->name('linkedin.')->group(function () {
    Route::get('redirect', [LinkedInAuthController::class, 'redirectToLinkedIn'])->name('redirect');
    Route::get('callback', [LinkedInAuthController::class, 'handleCallback'])->name('callback');
});
```

### Step 10 — Environment Variables

Add to `.env.example`:
```env
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_REDIRECT_URI="${APP_URL}/api/auth/linkedin/callback"
```

Add to `config/services.php`:
```php
'linkedin' => [
    'client_id'     => env('LINKEDIN_CLIENT_ID'),
    'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    'redirect'      => env('LINKEDIN_REDIRECT_URI'),
],
```

### Step 11 — Token Refresh Command

Create: `app/Console/Commands/RefreshLinkedInTokens.php`

LinkedIn tokens expire in 60 days. Refresh tokens last 1 year.

```php
// Find SocialAccounts for LinkedIn platform where token_expires_at < now() + 7 days
// Call LinkedIn token refresh endpoint:
// POST https://www.linkedin.com/oauth/v2/accessToken
//   grant_type=refresh_token&refresh_token=xxx&client_id=xxx&client_secret=xxx
// Update social_accounts: access_token, refresh_token, token_expires_at
// Log success/failure to activity_logs
```

Register in `app/Console/Commands/CheckTokenExpiry.php` (which already exists) or schedule independently in `routes/console.php`:
```php
Schedule::command('linkedin:refresh-tokens')->daily();
```

---

## CODE QUALITY REQUIREMENTS (from laravel-project-rules.md)

- All new classes must have **full PHP type hints** on every parameter and return type
- Use **readonly** constructor properties where state is not mutated
- All validation must live in **FormRequest** classes — never `$request->validate()` inline
- Token storage: **always** `encrypt()` on write, `decrypt()` on read — no plaintext tokens
- All API responses must use **Laravel API Resources** (`app/Http/Resources/`)
- Service methods must be **testable in isolation** — inject `GuzzleHttp\Client` via the constructor (or use `Http::fake()` patterns with Laravel HTTP client)
- Every new service must have a corresponding **Feature test** in `tests/Feature/LinkedIn/`
- Log all LinkedIn API calls that fail with `Log::error()` using context array matching existing patterns
- Follow existing **naming conventions** exactly (see rules file section 1.4)

---

## LINKEDIN API REFERENCE

- OAuth 2.0 docs: https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow
- UGC Posts API: https://learn.microsoft.com/en-us/linkedin/marketing/integrations/community-management/shares/ugc-post-api
- Assets API (image upload): https://learn.microsoft.com/en-us/linkedin/marketing/integrations/community-management/shares/vector-asset-api
- Organization APIs: https://learn.microsoft.com/en-us/linkedin/marketing/integrations/community-management/organizations/organization-lookup-api
- LinkedIn API base: `https://api.linkedin.com/v2/`
- Required header on all calls: `LinkedIn-Version: 202406`

---

## DELIVERABLES CHECKLIST

- [ ] `database/migrations/XXXX_seed_linkedin_social_platform.php`
- [ ] `database/migrations/XXXX_add_linkedin_columns_to_posts_table.php`
- [ ] `app/Http/Controllers/Api/LinkedInAuthController.php`
- [ ] `app/Services/LinkedInPostService.php`
- [ ] `app/Services/SocialAccountProvisioner.php` — updated with LinkedIn methods
- [ ] `app/Http/Controllers/Api/PostController.php` — updated with linkedin case
- [ ] `app/Console/Commands/RefreshLinkedInTokens.php`
- [ ] `app/Console/Commands/PublishScheduledPosts.php` — updated with linkedin case
- [ ] `config/services.php` — linkedin entry added
- [ ] `.env.example` — LinkedIn variables added
- [ ] `routes/api.php` — LinkedIn auth routes added
- [ ] `tests/Feature/LinkedIn/LinkedInAuthTest.php`
- [ ] `tests/Feature/LinkedIn/LinkedInPostServiceTest.php`

---

## IMPORTANT: DO NOT

- Do not create a new `users` column for LinkedIn (unlike Facebook which has `facebook_id` on users) — everything goes through `social_accounts`
- Do not use `$request->validate()` inline — always create a FormRequest
- Do not store tokens in plaintext — always `encrypt()`
- Do not add business logic to controllers
- Do not skip the `PostTarget` status update after publishing
- Do not duplicate the `resolveToken()` method — extract it to a shared trait or base class if it appears in 2+ services