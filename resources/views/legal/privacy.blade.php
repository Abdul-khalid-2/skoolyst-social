@extends('layouts.legal', [
    'pageTitle' => 'Privacy Policy',
    'metaDescription' => 'Privacy Policy for Skoolyst Social — how we collect, use, store, and protect data for schools using our social media management platform.',
    'lastUpdated' => $lastUpdated,
    'effectiveDate' => $effectiveDate,
    'businessName' => $businessName,
    'productName' => $productName,
    'contactEmail' => $contactEmail,
])

@section('content')
    <div class="prose prose-gray prose-headings:scroll-mt-24 max-w-none">
        <header class="not-prose mb-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 mt-0 mb-3">Privacy Policy</h1>
            <p class="text-gray-600 leading-relaxed">
                This Privacy Policy describes how <strong>{{ $businessName }}</strong> (“we,” “us,” or “our”) collects, uses, discloses, and protects personal information when you use <strong>{{ $productName }}</strong> (the “Service”), a social media management platform for schools and educational institutions, available at <a href="{{ $appUrl }}" class="text-blue-600 hover:underline">{{ $appUrl }}</a>.
            </p>
            <p class="text-sm text-gray-500 mt-4">
                <strong>Effective date:</strong> {{ $effectiveDate }}
            </p>
        </header>

        <section id="introduction" aria-labelledby="introduction-heading">
            <h2 id="introduction-heading" class="text-xl font-semibold text-gray-900">1. Introduction</h2>
            <p>
                We are committed to protecting your privacy and handling your data transparently. This policy applies to administrators, staff, and authorized users who register for or use the Service on behalf of a school or educational institution. By using the Service, you agree to the practices described in this Privacy Policy.
            </p>
        </section>

        <section id="data-controller" aria-labelledby="data-controller-heading">
            <h2 id="data-controller-heading" class="text-xl font-semibold text-gray-900">2. Data Controller</h2>
            <p>
                <strong>{{ $businessName }}</strong> is the data controller for personal information processed through the Service. We operate from Pakistan and serve users internationally.
            </p>
            <ul>
                <li><strong>Business name:</strong> {{ $businessName }}</li>
                <li><strong>Product:</strong> {{ $productName }}</li>
                <li><strong>Website:</strong> <a href="{{ $appUrl }}">{{ $appUrl }}</a></li>
                <li><strong>Privacy &amp; data protection contact:</strong> <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> (Data Protection Officer)</li>
            </ul>
        </section>

        <section id="information-we-collect" aria-labelledby="information-we-collect-heading">
            <h2 id="information-we-collect-heading" class="text-xl font-semibold text-gray-900">3. Information We Collect</h2>
            <p>We collect the following categories of information:</p>

            <h3 class="text-lg font-medium text-gray-900">3.1 Account and profile information</h3>
            <ul>
                <li>Full name, email address, and password (stored in hashed form)</li>
                <li>Profile picture URL or uploaded avatar, where provided</li>
                <li>Workspace and organization name associated with your school or institution</li>
                <li>Role and permissions within a workspace</li>
            </ul>

            <h3 class="text-lg font-medium text-gray-900">3.2 Social platform connection data</h3>
            <ul>
                <li>OAuth access tokens and refresh tokens for Facebook, Instagram (via Meta), and LinkedIn</li>
                <li>Platform-specific user IDs, page IDs, account IDs, and organization IDs</li>
                <li>Connected page or account names, profile metadata, and connection status</li>
                <li>Token expiration dates and scopes granted during authorization</li>
            </ul>

            <h3 class="text-lg font-medium text-gray-900">3.3 Content you create or schedule</h3>
            <ul>
                <li>Post text, captions, hashtags, and scheduling dates and times</li>
                <li>Images, videos, and other media uploaded for publishing</li>
                <li>Publishing status, error logs, and platform response identifiers</li>
            </ul>

            <h3 class="text-lg font-medium text-gray-900">3.4 Usage and technical data</h3>
            <ul>
                <li>IP address, browser type, device type, and operating system</li>
                <li>Session identifiers, login timestamps, and pages or features accessed</li>
                <li>Aggregated usage analytics to improve reliability and performance</li>
                <li>Server and application logs for security, debugging, and audit purposes</li>
            </ul>

            <h3 class="text-lg font-medium text-gray-900">3.5 Communications</h3>
            <ul>
                <li>Messages you send to us (support requests, data deletion requests, feedback)</li>
            </ul>
        </section>

        <section id="how-we-collect" aria-labelledby="how-we-collect-heading">
            <h2 id="how-we-collect-heading" class="text-xl font-semibold text-gray-900">4. How We Collect Information</h2>
            <ul>
                <li><strong>Direct input:</strong> When you register, update your profile, create posts, or change settings.</li>
                <li><strong>Facebook Login / Meta APIs:</strong> When you connect Facebook Pages or Instagram Business accounts, Meta provides identifiers, tokens, and permitted profile or page data according to the permissions you grant.</li>
                <li><strong>LinkedIn OAuth:</strong> When you connect a LinkedIn profile or organization page, LinkedIn provides identifiers, tokens, and permitted account data according to the permissions you grant.</li>
                <li><strong>Automated collection:</strong> Through cookies, session management, and standard server logs when you use the Service.</li>
                <li><strong>Third-party platforms:</strong> Limited data returned when we publish content or verify connections on your behalf.</li>
            </ul>
        </section>

        <section id="why-we-collect" aria-labelledby="why-we-collect-heading">
            <h2 id="why-we-collect-heading" class="text-xl font-semibold text-gray-900">5. Why We Use Your Information</h2>
            <p>We process personal information for the following purposes:</p>
            <ul>
                <li>To provide, operate, and maintain the Service</li>
                <li>To authenticate you and manage your account and workspace</li>
                <li>To connect and manage your Facebook, Instagram, and LinkedIn accounts</li>
                <li>To schedule, publish, and manage social media posts on your behalf, as you direct</li>
                <li>To display connection status, publishing history, and relevant dashboards</li>
                <li>To secure the Service, prevent fraud, and enforce our Terms of Service</li>
                <li>To comply with legal obligations and respond to lawful requests</li>
                <li>To improve the Service through aggregated, anonymized analytics</li>
                <li>To communicate with you about your account, security, or policy updates</li>
            </ul>
            <p>
                Our legal bases for processing under the GDPR (where applicable) include performance of a contract, legitimate interests (security, service improvement), consent (where required for optional features), and compliance with legal obligations.
            </p>
        </section>

        <section id="how-we-store" aria-labelledby="how-we-store-heading">
            <h2 id="how-we-store-heading" class="text-xl font-semibold text-gray-900">6. How We Store and Protect Information</h2>
            <ul>
                <li>Data is stored in a MySQL database hosted on <strong>Hostinger</strong> infrastructure.</li>
                <li>Passwords are hashed using industry-standard one-way algorithms; we do not store plain-text passwords.</li>
                <li>OAuth access tokens and other sensitive credentials are encrypted at rest where supported by our application configuration.</li>
                <li>Data in transit is protected using TLS/HTTPS.</li>
                <li>Access to production systems is restricted to authorized personnel on a need-to-know basis.</li>
            </ul>
            <p>
                While we implement reasonable technical and organizational safeguards, no method of transmission or storage is completely secure. You are responsible for maintaining the confidentiality of your login credentials.
            </p>
        </section>

        <section id="sharing" aria-labelledby="sharing-heading">
            <h2 id="sharing-heading" class="text-xl font-semibold text-gray-900">7. Sharing with Third Parties</h2>
            <p>We do not sell your personal information. We share data only as follows:</p>

            <h3 class="text-lg font-medium text-gray-900">7.1 Social platforms (Meta / Facebook, Instagram, LinkedIn)</h3>
            <p>
                When you connect an account and publish content, we transmit the information necessary to perform that action to the relevant platform (e.g., post text, media URLs, page or account identifiers, and access tokens). This sharing is limited to fulfilling your publishing instructions and maintaining authorized connections. Each platform processes data under its own privacy policy and terms.
            </p>

            <h3 class="text-lg font-medium text-gray-900">7.2 Infrastructure and service providers</h3>
            <p>
                We use hosting and infrastructure providers (including Hostinger) that process data on our behalf under contractual obligations to protect your information and use it only to provide services to us.
            </p>

            <h3 class="text-lg font-medium text-gray-900">7.3 Legal requirements</h3>
            <p>
                We may disclose information if required by law, court order, or governmental request, or when we believe disclosure is necessary to protect rights, safety, or the integrity of the Service.
            </p>
        </section>

        <section id="retention" aria-labelledby="retention-heading">
            <h2 id="retention-heading" class="text-xl font-semibold text-gray-900">8. Data Retention</h2>
            <ul>
                <li><strong>Account data:</strong> Retained while your account is active and for a reasonable period afterward to allow reactivation or resolve disputes, unless you request earlier deletion.</li>
                <li><strong>OAuth tokens:</strong> Retained until you disconnect a platform, delete your account, or we receive a valid deletion request; expired tokens are removed or refreshed as applicable.</li>
                <li><strong>Post content and media:</strong> Retained according to your workspace history and publishing needs; deleted when you remove posts or your account is deleted, subject to backup cycles.</li>
                <li><strong>Logs and security records:</strong> May be retained for up to 12–24 months for security, fraud prevention, and legal compliance, then anonymized or deleted.</li>
                <li><strong>Anonymized analytics:</strong> May be retained indefinitely in non-identifiable form.</li>
            </ul>
        </section>

        <section id="your-rights" aria-labelledby="your-rights-heading">
            <h2 id="your-rights-heading" class="text-xl font-semibold text-gray-900">9. Your Rights and Choices</h2>
            <p>Depending on your location, you may have the right to:</p>
            <ul>
                <li><strong>Access</strong> the personal information we hold about you</li>
                <li><strong>Correct</strong> inaccurate or incomplete information</li>
                <li><strong>Delete</strong> your personal information, subject to legal exceptions</li>
                <li><strong>Export</strong> your data in a portable format, where technically feasible</li>
                <li><strong>Restrict or object</strong> to certain processing, where applicable</li>
                <li><strong>Withdraw consent</strong> where processing is based on consent</li>
                <li><strong>Deactivate</strong> your account and disconnect social platforms at any time</li>
            </ul>
            <p>
                To exercise these rights, email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> or follow our
                <a href="{{ route('data-deletion') }}" class="text-blue-600 hover:underline">Data Deletion Instructions</a>.
                We will respond within the timeframes required by applicable law (typically within 30 days).
            </p>

            <h3 class="text-lg font-medium text-gray-900">9.1 GDPR (EEA, UK, and similar jurisdictions)</h3>
            <p>
                If you are in the European Economic Area or United Kingdom, you have additional rights under the GDPR, including the right to lodge a complaint with your local supervisory authority. We process data on lawful bases described in Section 5. For international transfers, we rely on appropriate safeguards where required.
            </p>

            <h3 class="text-lg font-medium text-gray-900">9.2 CCPA / CPRA (California residents)</h3>
            <p>
                California residents have the right to know what personal information we collect, request deletion, correct inaccuracies, and opt out of the “sale” or “sharing” of personal information. We do not sell personal information. To submit a verifiable consumer request, contact <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>. We will not discriminate against you for exercising your rights.
            </p>
        </section>

        <section id="data-deletion" aria-labelledby="data-deletion-heading">
            <h2 id="data-deletion-heading" class="text-xl font-semibold text-gray-900">10. Data Deletion</h2>
            <p>
                You may request deletion of your account and associated data at any time. Detailed instructions are available on our
                <a href="{{ route('data-deletion') }}" class="text-blue-600 hover:underline">Data Deletion</a> page, including in-app steps and email requests.
            </p>
            <p>
                When you remove Facebook or Instagram data through Meta’s app settings, Meta may send an automated data deletion callback to our systems; we process such requests in accordance with Meta’s platform requirements.
            </p>
        </section>

        <section id="cookies" aria-labelledby="cookies-heading">
            <h2 id="cookies-heading" class="text-xl font-semibold text-gray-900">11. Cookies and Similar Technologies</h2>
            <p>We use cookies and similar technologies to:</p>
            <ul>
                <li>Maintain your login session and remember preferences</li>
                <li>Protect against cross-site request forgery (CSRF)</li>
                <li>Understand aggregated usage patterns</li>
            </ul>
            <p>
                Essential cookies are required for the Service to function. You can control non-essential cookies through your browser settings; disabling essential cookies may prevent you from using authenticated features.
            </p>
        </section>

        <section id="children" aria-labelledby="children-heading">
            <h2 id="children-heading" class="text-xl font-semibold text-gray-900">12. Children’s Privacy</h2>
            <p>
                {{ $productName }} is designed for use by <strong>adult staff members</strong> (administrators, teachers, and authorized personnel) at schools and educational institutions—not by students or children under 13 (or the applicable age of digital consent in your jurisdiction).
            </p>
            <p>
                We do not knowingly collect personal information from children. If you believe a child has provided us personal information, contact us at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> and we will take steps to delete such information. Schools are responsible for ensuring that only authorized adults use the Service on their behalf, consistent with COPPA-style protections and local education privacy requirements.
            </p>
        </section>

        <section id="international" aria-labelledby="international-heading">
            <h2 id="international-heading" class="text-xl font-semibold text-gray-900">13. International Users</h2>
            <p>
                We are based in Pakistan and may process and store information in Pakistan and other countries where our service providers operate. By using the Service, you acknowledge that your information may be transferred to jurisdictions that may have different data protection laws than your country. Where required, we implement appropriate safeguards for cross-border transfers.
            </p>
        </section>

        <section id="changes" aria-labelledby="changes-heading">
            <h2 id="changes-heading" class="text-xl font-semibold text-gray-900">14. Changes to This Policy</h2>
            <p>
                We may update this Privacy Policy from time to time. When we make material changes, we will post the updated policy on this page with a revised “Last updated” date and, where appropriate, notify you by email or through an in-app notice. Continued use of the Service after changes become effective constitutes acceptance of the updated policy.
            </p>
        </section>

        <section id="contact" aria-labelledby="contact-heading">
            <h2 id="contact-heading" class="text-xl font-semibold text-gray-900">15. Contact Us</h2>
            <p>
                For privacy questions, data subject requests, or concerns about this policy, contact our Data Protection Officer:
            </p>
            <ul>
                <li><strong>Email:</strong> <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></li>
                <li><strong>Subject line (recommended):</strong> “Privacy Request — {{ $productName }}”</li>
            </ul>
            <p class="text-sm text-gray-500 not-prose mt-8 pt-6 border-t border-gray-100">
                See also: <a href="{{ route('legal.terms') }}" class="text-blue-600 hover:underline">Terms of Service</a>
                · <a href="{{ route('data-deletion') }}" class="text-blue-600 hover:underline">Data Deletion</a>
            </p>
        </section>
    </div>
@endsection
