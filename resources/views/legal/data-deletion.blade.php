@extends('layouts.legal', [
    'pageTitle' => 'Data Deletion Instructions',
    'metaDescription' => 'How to delete your data from Skoolyst Social — account deletion, OAuth tokens, posts, and Meta/Facebook data removal.',
    'lastUpdated' => $lastUpdated ?? 'May 15, 2026',
    'businessName' => $businessName ?? 'Skoolyst App',
    'productName' => $productName ?? 'Skoolyst Social',
    'contactEmail' => $contactEmail ?? 'abdulkhalidmasood@gmail.com',
])

@section('content')
    <div class="prose prose-gray prose-headings:scroll-mt-24 max-w-none">
        <header class="not-prose mb-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 mt-0 mb-3">Data Deletion Instructions</h1>
            <p class="text-gray-600 leading-relaxed">
                This page explains how to request deletion of personal data associated with your <strong>{{ $productName ?? 'Skoolyst Social' }}</strong> account operated by <strong>{{ $businessName ?? 'Skoolyst App' }}</strong>. We honor deletion requests in accordance with our <a href="{{ route('legal.privacy') }}" class="text-blue-600 hover:underline">Privacy Policy</a>, applicable law, and Meta (Facebook/Instagram) platform requirements.
            </p>
        </header>

        @if (! empty($code))
            <div
                class="not-prose mb-8 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900"
                role="status"
                aria-live="polite"
            >
                <p class="font-semibold text-emerald-950">Deletion request completed</p>
                <p class="mt-2 text-emerald-800">
                    Confirmation code:
                    <code class="rounded bg-white/90 px-2 py-1 font-mono text-xs border border-emerald-200">{{ e($code) }}</code>
                </p>
                <p class="mt-2 text-xs text-emerald-800/90 leading-relaxed">
                    If you started this from Facebook or Instagram settings, you may share this code with support if you need to verify completion. Facebook user identifiers, access tokens, and connected Facebook/Instagram publishing accounts tied to your Meta user have been removed from our systems.
                </p>
            </div>
        @endif

        <section id="overview" aria-labelledby="overview-heading">
            <h2 id="overview-heading" class="text-xl font-semibold text-gray-900">1. Overview</h2>
            <p>
                You can delete your data using either of the methods below. We process verified requests within <strong>30 days</strong> and will confirm completion by email when the request was submitted by email, or by displaying a confirmation code when deletion is initiated through Meta’s automated callback.
            </p>
        </section>

        <section id="method-in-app" aria-labelledby="method-in-app-heading">
            <h2 id="method-in-app-heading" class="text-xl font-semibold text-gray-900">2. Method A — In the application</h2>

            <h3 class="text-lg font-medium text-gray-900">2.1 Delete your entire account</h3>
            <ol class="list-decimal pl-5 space-y-2">
                <li>Sign in to <a href="{{ $appUrl ?? config('app.url') }}">{{ $appUrl ?? config('app.url') }}</a>.</li>
                <li>Open <strong>Settings</strong> from the sidebar.</li>
                <li>Go to the <strong>Account</strong> section.</li>
                <li>Select <strong>Delete Account</strong> and confirm when prompted.</li>
            </ol>
            <p>
                This permanently removes your user account, disconnects all linked social platforms, and schedules deletion of associated posts, media, and OAuth tokens from our database, subject to the retention exceptions in Section 5.
            </p>

            <h3 class="text-lg font-medium text-gray-900">2.2 Remove Facebook / Instagram data only (keep Skoolyst account)</h3>
            <p>If you only want to remove Meta-related data while keeping your Skoolyst login:</p>
            <ol class="list-decimal pl-5 space-y-2">
                <li>Sign in and open <strong>Settings → Integrations</strong>.</li>
                <li>Use <strong>Remove Facebook-connected data</strong> and confirm the checkbox.</li>
            </ol>
            <p>
                This removes your stored Facebook user ID, Facebook access tokens, API tokens issued for your user, and workspace Facebook/Instagram connections linked to that Meta account. Your Skoolyst account and other platform connections (e.g., LinkedIn) remain unless you delete them separately.
            </p>
        </section>

        <section id="method-email" aria-labelledby="method-email-heading">
            <h2 id="method-email-heading" class="text-xl font-semibold text-gray-900">3. Method B — Email request</h2>
            <p>If you cannot access the application, send an email to:</p>
            <p class="not-prose">
                <a
                    href="mailto:{{ $contactEmail ?? 'abdulkhalidmasood@gmail.com' }}?subject=Data%20Deletion%20Request"
                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 no-underline"
                >
                    {{ $contactEmail ?? 'abdulkhalidmasood@gmail.com' }}
                </a>
            </p>
            <p>Include the following:</p>
            <ul>
                <li><strong>Subject line:</strong> <code>Data Deletion Request</code></li>
                <li>The email address associated with your Skoolyst account</li>
                <li>Your school or organization name (if applicable)</li>
                <li>Whether you want full account deletion or removal of specific connected platforms</li>
                <li>Any relevant Facebook or Instagram confirmation code, if you initiated deletion from Meta</li>
            </ul>
            <p>
                We may ask you to verify ownership of the account before processing. Deletion will be completed within <strong>30 days</strong> of verification.
            </p>
        </section>

        <section id="meta-facebook" aria-labelledby="meta-facebook-heading">
            <h2 id="meta-facebook-heading" class="text-xl font-semibold text-gray-900">4. Deletion via Facebook or Instagram settings</h2>
            <p>
                You can remove our app and request data deletion from your Facebook account settings (Settings → Apps and Websites). Meta may send an automated signed request to our data deletion callback. We validate the request, delete associated data, and return a status URL with a confirmation code—which you can view on this page.
            </p>
            @isset($metaCallbackUrl)
                <p class="text-sm text-gray-600">
                    <strong>For Meta App Review (callback URL):</strong>
                </p>
                <p class="not-prose">
                    <code class="block break-all rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-800 border border-gray-200">{{ e($metaCallbackUrl) }}</code>
                </p>
            @endisset
        </section>

        <section id="what-is-deleted" aria-labelledby="what-is-deleted-heading">
            <h2 id="what-is-deleted-heading" class="text-xl font-semibold text-gray-900">5. What is deleted</h2>
            <p>Upon a full account deletion request, we delete or anonymize:</p>
            <ul>
                <li>Your user profile (name, email, password hash, profile picture reference)</li>
                <li>OAuth access tokens and refresh tokens for Facebook, Instagram, and LinkedIn</li>
                <li>Connected social account records (page IDs, account names, platform user IDs)</li>
                <li>Scheduled and published post content, captions, and uploaded media stored on our servers</li>
                <li>Workspace membership associations tied solely to your personal account, where applicable</li>
                <li>Active API and session tokens issued for your user</li>
            </ul>

            <h3 class="text-lg font-medium text-gray-900">5.1 What may be retained</h3>
            <p>We may retain limited information where required by law or legitimate business needs:</p>
            <ul>
                <li><strong>Audit and security logs</strong> (e.g., IP address, timestamp of deletion request) for fraud prevention and legal compliance, typically up to 12–24 months</li>
                <li><strong>Anonymized, aggregated analytics</strong> that cannot identify you</li>
                <li><strong>Billing records</strong> if you had a paid subscription, as required for tax and accounting laws</li>
            </ul>
        </section>

        <section id="platforms" aria-labelledby="platforms-heading">
            <h2 id="platforms-heading" class="text-xl font-semibold text-gray-900">6. Connected platforms</h2>
            <p>
                Deleting data from Skoolyst removes tokens and connection records from our database and stops our ability to publish on your behalf. Content already published to Facebook, Instagram, or LinkedIn remains on those platforms until you delete it there directly. Where platform APIs allow, disconnection may revoke our app’s access; we recommend also removing the app in each platform’s connected apps settings.
            </p>
        </section>

        <section id="timeline" aria-labelledby="timeline-heading">
            <h2 id="timeline-heading" class="text-xl font-semibold text-gray-900">7. Response timeline</h2>
            <ul>
                <li><strong>Meta automated callbacks:</strong> Processed promptly; confirmation code provided immediately in our JSON response and on this page.</li>
                <li><strong>In-app deletion:</strong> Processed as soon as you confirm; backups purged on our regular backup cycle (within 30 days).</li>
                <li><strong>Email requests:</strong> Acknowledged within 7 business days; completed within 30 days of identity verification.</li>
            </ul>
        </section>

        <section id="contact" aria-labelledby="contact-heading">
            <h2 id="contact-heading" class="text-xl font-semibold text-gray-900">8. Questions</h2>
            <p>
                For help with deletion or to check the status of a request, email
                <a href="mailto:{{ $contactEmail ?? 'abdulkhalidmasood@gmail.com' }}">{{ $contactEmail ?? 'abdulkhalidmasood@gmail.com' }}</a>
                with subject “Data Deletion Request” and include your confirmation code if you have one.
            </p>
            <p class="text-sm text-gray-500 not-prose mt-8 pt-6 border-t border-gray-100">
                See also: <a href="{{ route('legal.privacy') }}" class="text-blue-600 hover:underline">Privacy Policy</a>
                · <a href="{{ route('legal.terms') }}" class="text-blue-600 hover:underline">Terms of Service</a>
            </p>
        </section>
    </div>
@endsection
