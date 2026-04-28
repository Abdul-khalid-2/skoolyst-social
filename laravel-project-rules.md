# Laravel Project Rules — AI Coding Assistant Guide
# Version: 1.0 | Compatible: Laravel 10/11 | PHP 8.2+
# Use this file in: Cursor (.cursorrules), Windsurf, GitHub Copilot, ChatGPT Projects, Claude Projects
# ─────────────────────────────────────────────────────────────────────────────

## ROLE & MISSION

You are a senior Laravel developer assistant. Every suggestion, code change,
new feature, or refactor you make MUST follow ALL rules in this file.
Never skip a rule for convenience. If two rules conflict, ask before proceeding.
Apply these rules equally to new projects AND upgrades to existing code.

---

## 1. ARCHITECTURE & CODE QUALITY

### 1.1 DRY — Don't Repeat Yourself
- NEVER copy-paste logic. If the same code appears twice, extract it.
- Shared logic → Service class, Trait, Helper function, or Base class.
- Shared UI → Blade Component, Partial, or Layout.
- Before writing new code, always check if a similar class/method exists.

### 1.2 Layer Separation (always enforce)
```
Controller   → HTTP only: validate, call service, return response. NO business logic.
Service      → Business logic, orchestration, calls to repositories/APIs.
Repository   → Database queries only. No business logic.
Model        → Relationships, casts, scopes, fillable. Nothing else.
Job/Action   → Single focused task (SendEmail, CreatePost, SchedulePost).
FormRequest  → All validation rules. Never use $request->validate() inline.
Resource     → API JSON shaping. Always use for API responses.
```

### 1.3 Single Responsibility
- Every class does ONE thing. If you can't describe it in one sentence, split it.
- Action classes: `app/Actions/Post/CreatePostAction.php`
- Keep controllers thin — max 5-7 lines per method.

### 1.4 Naming Conventions
```
Models:           Singular, PascalCase         → Post, ScheduledPost, FacebookToken
Controllers:      Plural resource, PascalCase   → PostsController, TokensController
Services:         Descriptive + Service         → FacebookPostingService, SchedulerService
Jobs:             Verb + Noun                   → ProcessScheduledPost, RefreshFacebookToken
FormRequests:     Action + Request              → StorePostRequest, UpdateScheduleRequest
Resources:        Model + Resource              → PostResource, UserResource
Migrations:       snake_case descriptive        → create_scheduled_posts_table
```

### 1.5 Type Safety
- Always add PHP type hints: parameters, return types, property types.
- Use PHP 8.2+ features: readonly properties, enums, fibers where applicable.
- Use Enums instead of magic strings:
  ```php
  // BAD
  $post->status = 'scheduled';
  // GOOD
  $post->status = PostStatus::SCHEDULED;
  ```

---

## 2. FILE & FOLDER STRUCTURE

```
app/
├── Actions/          # Single-purpose action classes
│   └── Post/
│       ├── CreatePostAction.php
│       └── SchedulePostAction.php
├── Enums/            # PHP 8.1+ Enums
│   ├── PostStatus.php
│   └── Platform.php
├── Http/
│   ├── Controllers/  # Thin controllers only
│   ├── Middleware/
│   ├── Requests/     # FormRequest classes (validation here ONLY)
│   └── Resources/    # API Resources
├── Models/           # Eloquent models
├── Repositories/     # DB query logic
│   └── Contracts/    # Repository interfaces
├── Services/         # Business logic
│   └── Facebook/
│       ├── FacebookAuthService.php
│       └── FacebookPostingService.php
├── Jobs/             # Queue jobs
├── Events/ & Listeners/
├── Observers/        # Model observers
└── Traits/           # Reusable PHP traits

resources/
├── views/
│   ├── components/   # Blade components (<x-button>, <x-card>)
│   ├── layouts/      # App layout files
│   ├── partials/     # Reusable view partials
│   └── pages/        # Page views organized by feature
└── js/ & css/

routes/
├── web.php
├── api/
│   ├── v1.php        # Always version your API
│   └── v2.php
└── channels.php
```

---

## 3. BLADE COMPONENTS (UI REUSABILITY)

### 3.1 Always check existing components first
Before writing ANY HTML for a button, card, alert, form, input — check
`resources/views/components/` for an existing component.

### 3.2 Required components to build and reuse
Build these components if they don't exist. Use them EVERYWHERE:
```blade
<x-button>            → All buttons (variants: primary, secondary, danger, ghost)
<x-card>              → All card containers
<x-alert>             → All alerts/notifications (success, error, warning, info)
<x-input>             → All text inputs
<x-textarea>          → All textareas
<x-select>            → All dropdowns
<x-badge>             → Status badges
<x-modal>             → Modal dialogs
<x-avatar>            → User avatars
<x-empty-state>       → Empty list states
<x-page-header>       → Page titles + breadcrumbs
<x-data-table>        → Table with sorting/pagination
<x-form-error>        → Field-level error messages
<x-loading-spinner>   → Loading states
```

### 3.3 Component rules
- NEVER hardcode a button as `<button class="...">` in a page view.
  Always use `<x-button>` and let the component handle styling.
- Components MUST accept `$attributes` merge for extensibility:
  ```php
  <button {{ $attributes->merge(['class' => 'btn btn-primary']) }}>
  ```
- Components with multiple variants use a `$variant` prop.

---

## 4. PERFORMANCE & OPTIMIZATION

### 4.1 Database (most critical)
- ALWAYS use eager loading. Never lazy load in loops.
  ```php
  // BAD — N+1 query
  $posts = Post::all();
  foreach ($posts as $post) { $post->user->name; }

  // GOOD
  $posts = Post::with(['user', 'platform'])->get();
  ```
- Always paginate. NEVER use `->get()` on large tables without a LIMIT.
  ```php
  ->paginate(20)        // regular pagination
  ->cursorPaginate(20)  // for infinite scroll / large datasets
  ```
- Add database indexes on: foreign keys, search columns, status columns,
  scheduled_at, created_at (when filtering by date).
- Use `->select(['id', 'title', 'status'])` — don't SELECT * when you need 3 columns.
- Use `->chunk(200, fn($batch) => ...)` or `->lazy()` for bulk operations.
- Wrap multiple related writes in `DB::transaction()`.

### 4.2 Caching
- Cache expensive queries with `Cache::remember()`.
- Use Redis for session, cache, and queues in production.
- Cache keys must be descriptive and versioned:
  `"user:{$userId}:facebook_pages"` not `"data"`.
- Always set a TTL. Never cache indefinitely without a reason.

### 4.3 Queues (mandatory for heavy tasks)
- These MUST go in Queue Jobs — never run synchronously in a request:
  - Sending emails
  - API calls (Facebook Graph API, any external service)
  - Image/file processing
  - Bulk database operations
  - Generating reports
- Use Laravel Horizon for monitoring in production.
- Set appropriate `tries`, `backoff`, and `timeout` on every job.

### 4.4 Asset Optimization
- Use Vite (not Mix) for JS/CSS bundling.
- Lazy-load images: `<img loading="lazy" ...>` always.
- Use `<x-img>` component that auto-adds `loading="lazy"` and `alt`.
- Run `php artisan route:cache && config:cache && view:cache` in production.

---

## 5. SECURITY

### 5.1 Authentication & OAuth
- NEVER store raw user passwords for third-party services.
- ALWAYS use OAuth 2.0 for connecting external accounts (Facebook, Google, etc.).
- Store only access tokens, never email/password credentials.
- Encrypt sensitive tokens before storing in DB:
  `protected $casts = ['access_token' => 'encrypted'];`
- Always handle token expiry — refresh tokens automatically before API calls.

### 5.2 Input & Output
- All validation in FormRequest classes. Never in controllers.
- Always use Eloquent / Query Builder. Raw SQL only when absolutely necessary,
  and always with parameter binding.
- In Blade: use `{{ }}` (auto-escaped) everywhere.
  Only use `{!! !!}` for intentionally trusted HTML (e.g. rendered Markdown).
- Sanitize file uploads: validate MIME type, extension, and size.

### 5.3 Rate Limiting & Middleware
- Apply `throttle` middleware to all API routes and login endpoints.
- Apply `auth` middleware to all protected routes — never rely on frontend only.
- Apply HTTPS in production with `ForceHttps` middleware or server config.

### 5.4 Configuration & Secrets
- NEVER hardcode secrets, API keys, or credentials in source code.
- Always use `.env` + `config()` helper.
- `.env` must be in `.gitignore` — verify before every push.
- Use `php artisan env:encrypt` for CI/CD environments.

---

## 6. SEO & META

### 6.1 Every page must have
```blade
<title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>
<meta name="description" content="{{ $description ?? '' }}">
<link rel="canonical" href="{{ url()->current() }}">

<!-- Open Graph -->
<meta property="og:title" content="{{ $title ?? config('app.name') }}">
<meta property="og:description" content="{{ $description ?? '' }}">
<meta property="og:image" content="{{ $ogImage ?? asset('images/og-default.jpg') }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:type" content="website">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
```

### 6.2 Use a dedicated SEO component
```blade
<x-seo :title="$post->title" :description="$post->excerpt" :image="$post->og_image" />
```

### 6.3 Technical SEO
- Generate sitemap with `spatie/laravel-sitemap`.
- Configure `robots.txt` — block admin/api routes.
- Ensure clean, descriptive URLs (slugs not IDs where possible).
- Use structured data (JSON-LD) for content-heavy pages.

---

## 7. ACCESSIBILITY (a11y)

### 7.1 Mandatory on every element
- All `<img>` tags must have descriptive `alt` attribute (never empty unless decorative).
- All form inputs must have associated `<label>` (use `for` attribute or wrap).
- All icon-only buttons must have `aria-label`.
- All modals must trap focus and have `role="dialog"` + `aria-modal="true"`.
- All alerts must use `role="alert"` or `aria-live="polite"`.

### 7.2 Keyboard & Screen Reader
- Tab order must be logical — test with keyboard only.
- Provide skip links: `<a href="#main-content" class="sr-only focus:not-sr-only">`.
- Color must NOT be the only indicator of state — add icons, labels, or patterns.
- Minimum contrast ratio: 4.5:1 for normal text, 3:1 for large text (WCAG AA).

### 7.3 Semantic HTML
```html
<!-- ALWAYS use semantic tags -->
<header>, <nav>, <main>, <aside>, <footer>, <article>, <section>
<h1> (one per page), <h2>, <h3> (logical hierarchy — never skip levels)
<button> for actions, <a> for navigation (never swap these)
<ul>/<ol> for lists, <table> only for tabular data
```

---

## 8. DATABASE BEST PRACTICES

- Every schema change MUST have a migration. Never edit DB directly.
- All tables must have: `id`, `created_at`, `updated_at`.
- Use `SoftDeletes` trait on important data models (posts, users, connections).
- Migrations must be reversible — always implement `down()`.
- Factories + Seeders for all models — realistic fake data.
- Use Model Scopes for reusable query filters:
  ```php
  public function scopeScheduled($query): Builder
  public function scopeDueNow($query): Builder
  public function scopeForPlatform($query, Platform $platform): Builder
  ```
- Set up automated backups: `spatie/laravel-backup`.

---

## 9. API DESIGN

- Version all APIs from day one: `/api/v1/...`
- Always return JSON via Resource classes — never `Model::toArray()`.
- Consistent error response format:
  ```json
  { "message": "Validation failed", "errors": { "field": ["error"] }, "code": 422 }
  ```
- Use proper HTTP status codes: 200, 201, 204, 400, 401, 403, 404, 422, 429, 500.
- Document API with `knuckleswtf/scribe` or Laravel API docs package.
- Apply rate limiting on all API routes.

---

## 10. TESTING

- Write tests for every new feature — no exceptions.
- Minimum coverage: all Service methods, all API endpoints, all critical flows.
- Use factories — never seed test data manually.
- Feature tests for HTTP flows. Unit tests for Services, Actions, Helpers.
- Mock external API calls (Facebook Graph, etc.) in tests — never hit real APIs.
  ```php
  Http::fake(['graph.facebook.com/*' => Http::response([...])]);
  ```
- Run tests before every commit: `php artisan test`.

---

## 11. ERROR HANDLING & LOGGING

- Never expose raw exceptions to users in production.
- Use custom Exception classes: `FacebookTokenExpiredException`, `PostingFailedException`.
- Log all external API failures with context:
  ```php
  Log::error('Facebook posting failed', [
      'user_id' => $user->id,
      'post_id' => $post->id,
      'error'   => $e->getMessage(),
  ]);
  ```
- Set up Sentry or Flare for production error tracking.
- Use `report()` for non-fatal errors that should still be logged.

---

## 12. QUEUE & SCHEDULER RULES

- Every Job must define: `$tries`, `$backoff`, `$timeout`, `$queue`.
- Jobs must be idempotent — safe to retry on failure.
- Always use `onQueue('name')` to separate job types.
- Scheduled commands go in `app/Console/Kernel.php` with clear comments.
- Use `withoutOverlapping()` for recurring scheduled tasks.
- Test jobs with `Bus::fake()` — never dispatch real jobs in tests.

---

## 13. CODE REVIEW CHECKLIST
Before marking any task as complete, verify:

- [ ] No business logic in Controllers
- [ ] All validation in FormRequest
- [ ] No N+1 queries (check with Debugbar or Telescope)
- [ ] No raw passwords or secrets in code
- [ ] Existing Blade components used (no duplicate HTML)
- [ ] All images have alt text
- [ ] All inputs have labels
- [ ] Response uses Resource class (for API)
- [ ] Migration created for any DB change
- [ ] Feature test written
- [ ] No `->get()` without pagination on large tables
- [ ] Queued all external API calls
- [ ] `.env` not committed

---

## 14. PACKAGES — PREFERRED STACK

```
Authentication:   Laravel Breeze / Fortify / Sanctum / Passport
OAuth:            Laravel Socialite + socialiteproviders/*
Authorization:    spatie/laravel-permission
Media:            spatie/laravel-medialibrary
SEO/Sitemap:      spatie/laravel-sitemap
Backups:          spatie/laravel-backup
Settings:         spatie/laravel-settings
Activity Log:     spatie/laravel-activitylog
API Docs:         knuckleswtf/scribe
Monitoring:       Laravel Telescope (dev), Laravel Horizon (queues)
Error Tracking:   Sentry (flightsaber/sentry-laravel) or Flare
Code Style:       Laravel Pint
Frontend:         Vite + Tailwind CSS + Alpine.js
Testing:          Pest PHP (preferred) or PHPUnit
```

---

## 15. WHEN MODIFYING EXISTING CODE

Before making ANY change to existing code:
1. Read and understand the existing pattern — follow it unless it violates these rules.
2. If existing code violates a rule, refactor it as part of the task.
3. Do not introduce a new pattern if one already exists in the codebase.
4. Check for existing Blade components before creating new UI elements.
5. Check for existing Service/Action classes before writing new logic.
6. Run tests after every change: `php artisan test`.
7. Do not remove or alter existing tests — only add new ones.

---

*This file is your permanent guide. Apply every rule to every task, every time.*
