@extends('layouts.legal', [
    'pageTitle' => 'Terms of Service',
    'metaDescription' => 'Terms of Service for Skoolyst Social — rules and conditions for using our social media management platform for schools.',
    'lastUpdated' => $lastUpdated,
    'effectiveDate' => $effectiveDate,
    'businessName' => $businessName,
    'productName' => $productName,
    'contactEmail' => $contactEmail,
])

@section('content')
    <div class="prose prose-gray prose-headings:scroll-mt-24 max-w-none">
        <header class="not-prose mb-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 mt-0 mb-3">Terms of Service</h1>
            <p class="text-gray-600 leading-relaxed">
                These Terms of Service (“Terms”) govern your access to and use of <strong>{{ $productName }}</strong>, operated by <strong>{{ $businessName }}</strong> (“we,” “us,” or “our”), available at <a href="{{ $appUrl }}" class="text-blue-600 hover:underline">{{ $appUrl }}</a>. Please read these Terms carefully before using the Service.
            </p>
            <p class="text-sm text-gray-500 mt-4">
                <strong>Effective date:</strong> {{ $effectiveDate }}
            </p>
        </header>

        <section id="acceptance" aria-labelledby="acceptance-heading">
            <h2 id="acceptance-heading" class="text-xl font-semibold text-gray-900">1. Acceptance of Terms</h2>
            <p>
                By creating an account, accessing, or using the Service, you agree to be bound by these Terms and our <a href="{{ route('legal.privacy') }}" class="text-blue-600 hover:underline">Privacy Policy</a>. If you are using the Service on behalf of a school or organization, you represent that you have authority to bind that organization. If you do not agree, do not use the Service.
            </p>
        </section>

        <section id="service-description" aria-labelledby="service-description-heading">
            <h2 id="service-description-heading" class="text-xl font-semibold text-gray-900">2. Service Description</h2>
            <p>
                {{ $productName }} is a software-as-a-service (SaaS) platform that enables schools and educational institutions to connect social media accounts (including Facebook Pages, Instagram Business accounts, and LinkedIn profiles or organization pages), compose content, schedule posts, and publish updates on behalf of authorized users. Features may change over time; we may add, modify, or discontinue features with reasonable notice where practicable.
            </p>
        </section>

        <section id="accounts" aria-labelledby="accounts-heading">
            <h2 id="accounts-heading" class="text-xl font-semibold text-gray-900">3. User Accounts and Registration</h2>
            <ul>
                <li>You must provide accurate, current, and complete registration information.</li>
                <li>You are responsible for safeguarding your password and all activity under your account.</li>
                <li>You must notify us promptly at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> if you suspect unauthorized access.</li>
                <li>One person may not share login credentials; access should be limited to authorized staff of your institution.</li>
                <li>We may suspend or terminate accounts that violate these Terms or pose a security risk.</li>
            </ul>
        </section>

        <section id="acceptable-use" aria-labelledby="acceptable-use-heading">
            <h2 id="acceptable-use-heading" class="text-xl font-semibold text-gray-900">4. Acceptable Use Policy</h2>
            <p>You agree not to use the Service to:</p>
            <ul>
                <li>Violate any applicable law, regulation, or third-party rights</li>
                <li>Publish spam, misleading content, malware, or unsolicited commercial messages</li>
                <li>Harass, defame, threaten, or discriminate against others</li>
                <li>Infringe intellectual property, privacy, or publicity rights</li>
                <li>Impersonate any person or entity, or misrepresent your affiliation</li>
                <li>Attempt to gain unauthorized access to the Service, other accounts, or connected platforms</li>
                <li>Circumvent rate limits, API restrictions, or security measures</li>
                <li>Use the Service for any purpose other than legitimate school or institutional communications</li>
            </ul>
            <p>
                You must comply with the terms, policies, and community standards of each connected platform, including Meta’s Facebook Community Standards and Platform Terms, Instagram’s Terms of Use, and LinkedIn’s User Agreement and Professional Community Policies. We may remove content or suspend access if we reasonably believe you have violated these Terms or platform rules.
            </p>
        </section>

        <section id="user-content" aria-labelledby="user-content-heading">
            <h2 id="user-content-heading" class="text-xl font-semibold text-gray-900">5. User Content and Ownership</h2>
            <p>
                You retain all ownership rights in content you create, upload, or schedule through the Service (“User Content”). By using the Service, you grant {{ $businessName }} a limited, non-exclusive, worldwide, royalty-free license to host, store, reproduce, and transmit User Content solely as necessary to operate the Service—including publishing to connected social platforms at your direction. This license ends when User Content is deleted from our systems, subject to reasonable backup retention.
            </p>
            <p>
                You represent that you have all rights necessary to publish User Content and that it does not violate these Terms or any third-party rights. You are solely responsible for User Content and the consequences of publishing it.
            </p>
        </section>

        <section id="third-party-platforms" aria-labelledby="third-party-platforms-heading">
            <h2 id="third-party-platforms-heading" class="text-xl font-semibold text-gray-900">6. Third-Party Platforms</h2>
            <p>
                The Service integrates with third-party platforms that are not controlled by us. Your use of Facebook, Instagram, LinkedIn, and other connected services is governed by their respective terms and policies. We are not responsible for actions taken by those platforms, including content removal, account restrictions, or API changes. You authorize us to access and use platform APIs on your behalf according to the permissions you grant during OAuth connection.
            </p>
        </section>

        <section id="payment" aria-labelledby="payment-heading">
            <h2 id="payment-heading" class="text-xl font-semibold text-gray-900">7. Payment and Subscriptions</h2>
            <p>
                Certain features may require a paid subscription. Pricing, billing cycles, and plan limits will be presented at the time of purchase or in your account dashboard. Unless otherwise stated:
            </p>
            <ul>
                <li>Fees are quoted in the currency displayed at checkout and are non-refundable except where required by law or explicitly stated in writing.</li>
                <li>Subscriptions renew automatically unless cancelled before the renewal date.</li>
                <li>We may change pricing with advance notice; continued use after the effective date constitutes acceptance of new pricing for renewal periods.</li>
                <li>Failure to pay may result in suspension or downgrade of the Service.</li>
            </ul>
            <p class="text-sm text-gray-600">
                <em>Note: If your organization is on a trial, custom, or institutional plan, specific billing terms provided in your agreement with us will prevail over this section where they conflict.</em>
            </p>
        </section>

        <section id="termination" aria-labelledby="termination-heading">
            <h2 id="termination-heading" class="text-xl font-semibold text-gray-900">8. Termination</h2>
            <p>
                You may stop using the Service and request account deletion at any time (see our <a href="{{ route('data-deletion') }}" class="text-blue-600 hover:underline">Data Deletion</a> page). We may suspend or terminate your access immediately if you breach these Terms, if required by law, if a platform revokes necessary permissions, or if we discontinue the Service. Upon termination, your right to use the Service ceases; provisions that by their nature should survive (including ownership, disclaimers, limitation of liability, and indemnification) will survive.
            </p>
        </section>

        <section id="disclaimers" aria-labelledby="disclaimers-heading">
            <h2 id="disclaimers-heading" class="text-xl font-semibold text-gray-900">9. Disclaimers</h2>
            <p>
                THE SERVICE IS PROVIDED “AS IS” AND “AS AVAILABLE” WITHOUT WARRANTIES OF ANY KIND, WHETHER EXPRESS, IMPLIED, OR STATUTORY, INCLUDING IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE, AND NON-INFRINGEMENT. WE DO NOT WARRANT THAT THE SERVICE WILL BE UNINTERRUPTED, ERROR-FREE, SECURE, OR THAT POSTS WILL ALWAYS BE DELIVERED OR DISPLAYED ON THIRD-PARTY PLATFORMS. YOU USE THE SERVICE AT YOUR OWN RISK.
            </p>
        </section>

        <section id="limitation" aria-labelledby="limitation-heading">
            <h2 id="limitation-heading" class="text-xl font-semibold text-gray-900">10. Limitation of Liability</h2>
            <p>
                TO THE MAXIMUM EXTENT PERMITTED BY LAW, {{ strtoupper($businessName) }} AND ITS OFFICERS, DIRECTORS, EMPLOYEES, AND AGENTS SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR ANY LOSS OF PROFITS, DATA, GOODWILL, OR BUSINESS OPPORTUNITIES, ARISING FROM YOUR USE OF THE SERVICE. OUR TOTAL LIABILITY FOR ANY CLAIM ARISING OUT OF THESE TERMS OR THE SERVICE SHALL NOT EXCEED THE GREATER OF (A) THE AMOUNT YOU PAID US IN THE TWELVE (12) MONTHS BEFORE THE CLAIM, OR (B) ONE HUNDRED U.S. DOLLARS (USD $100).
            </p>
            <p>
                Some jurisdictions do not allow certain limitations; in those cases, our liability is limited to the fullest extent permitted by law.
            </p>
        </section>

        <section id="indemnification" aria-labelledby="indemnification-heading">
            <h2 id="indemnification-heading" class="text-xl font-semibold text-gray-900">11. Indemnification</h2>
            <p>
                You agree to indemnify, defend, and hold harmless {{ $businessName }} and its affiliates from any claims, damages, losses, liabilities, and expenses (including reasonable attorneys’ fees) arising from your User Content, your use of the Service, your violation of these Terms, or your violation of any third-party rights or platform policies.
            </p>
        </section>

        <section id="governing-law" aria-labelledby="governing-law-heading">
            <h2 id="governing-law-heading" class="text-xl font-semibold text-gray-900">12. Governing Law</h2>
            <p>
                These Terms are governed by the laws of the <strong>Islamic Republic of Pakistan</strong>, without regard to conflict-of-law principles. If you access the Service from outside Pakistan, you are responsible for compliance with local laws. Nothing in these Terms limits mandatory consumer protections that apply in your jurisdiction.
            </p>
        </section>

        <section id="dispute-resolution" aria-labelledby="dispute-resolution-heading">
            <h2 id="dispute-resolution-heading" class="text-xl font-semibold text-gray-900">13. Dispute Resolution</h2>
            <p>
                Before initiating formal proceedings, you agree to contact us at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> and attempt in good faith to resolve the dispute informally within thirty (30) days. If the dispute is not resolved, it shall be subject to the exclusive jurisdiction of the courts located in Pakistan, unless applicable law requires otherwise. You waive any right to participate in a class action to the extent permitted by law.
            </p>
        </section>

        <section id="general" aria-labelledby="general-heading">
            <h2 id="general-heading" class="text-xl font-semibold text-gray-900">14. General Provisions</h2>
            <ul>
                <li><strong>Entire agreement:</strong> These Terms and the Privacy Policy constitute the entire agreement regarding the Service.</li>
                <li><strong>Severability:</strong> If any provision is unenforceable, the remainder remains in effect.</li>
                <li><strong>Waiver:</strong> Failure to enforce a provision is not a waiver of future enforcement.</li>
                <li><strong>Assignment:</strong> You may not assign these Terms without our consent; we may assign them in connection with a merger or sale.</li>
                <li><strong>Changes:</strong> We may update these Terms; material changes will be posted on this page with an updated date. Continued use after changes constitutes acceptance.</li>
            </ul>
        </section>

        <section id="contact" aria-labelledby="contact-heading">
            <h2 id="contact-heading" class="text-xl font-semibold text-gray-900">15. Contact</h2>
            <p>For questions about these Terms, contact:</p>
            <ul>
                <li><strong>{{ $businessName }}</strong> — {{ $productName }}</li>
                <li><strong>Email:</strong> <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></li>
                <li><strong>Website:</strong> <a href="{{ $appUrl }}">{{ $appUrl }}</a></li>
            </ul>
            <p class="text-sm text-gray-500 not-prose mt-8 pt-6 border-t border-gray-100">
                See also: <a href="{{ route('legal.privacy') }}" class="text-blue-600 hover:underline">Privacy Policy</a>
                · <a href="{{ route('data-deletion') }}" class="text-blue-600 hover:underline">Data Deletion</a>
            </p>
        </section>
    </div>
@endsection
