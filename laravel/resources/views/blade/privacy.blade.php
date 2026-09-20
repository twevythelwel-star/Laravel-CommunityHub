@extends('layouts.public')

@section('title', 'Privacy Policy — ' . ($branding->app_name ?? 'Community Hub'))
@section('description', 'How Community Hub collects, uses, shares and protects resident data.')

@section('content')
<article class="mx-auto max-w-3xl px-4 py-12">

    <header class="mb-10">
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Privacy Policy</h1>
        <p class="mt-2 text-sm text-muted-foreground">Last updated: April 2026</p>
    </header>

    <div class="space-y-10">

        <section aria-labelledby="collect">
            <h2 id="collect" class="text-xl font-semibold tracking-tight">1. Information We Collect</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-relaxed text-muted-foreground">
                <li><strong class="text-foreground">Account details:</strong> name, email address, phone number, and role within the community.</li>
                <li><strong class="text-foreground">Community activity:</strong> visitor logs, gate pass usage, safety alerts, and billing records.</li>
                <li><strong class="text-foreground">Communications:</strong> feedback and messages submitted through the app.</li>
                <li><strong class="text-foreground">AI-generated content:</strong> documents you provide for AI-assisted notification generation.</li>
                <li><strong class="text-foreground">Technical data:</strong> browser type, device type, and session information.</li>
            </ul>
        </section>

        <section aria-labelledby="use">
            <h2 id="use" class="text-xl font-semibold tracking-tight">2. How We Use Your Information</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-relaxed text-muted-foreground">
                <li>To provide and improve the Community Hub platform and its features.</li>
                <li>To send community-wide notifications and announcements you have authorised.</li>
                <li>To process billing and HOA dues management.</li>
                <li>To maintain security records, visitor logs, and access control.</li>
                <li>To power AI-assisted features (where you explicitly opt in).</li>
            </ul>
        </section>

        <section aria-labelledby="share">
            <h2 id="share" class="text-xl font-semibold tracking-tight">3. Information Sharing</h2>
            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                We do not sell your personal information. We share it only with:
            </p>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-relaxed text-muted-foreground">
                <li>Google LLC — for AI-assisted features (with your explicit consent).</li>
                <li>Service providers necessary to operate the platform (e.g., hosting, authentication).</li>
                <li>Law enforcement or regulators when required by applicable law.</li>
            </ul>
        </section>

        <section aria-labelledby="rights">
            <h2 id="rights" class="text-xl font-semibold tracking-tight">4. Your Rights</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-relaxed text-muted-foreground">
                <li><strong class="text-foreground">Access:</strong> You may request a copy of the data we hold about you.</li>
                <li><strong class="text-foreground">Correction:</strong> You may update your profile information in the Settings page.</li>
                <li><strong class="text-foreground">Deletion:</strong> You may permanently delete your account via Settings &gt; Deactivation.</li>
                <li><strong class="text-foreground">Portability:</strong> Contact us to request a data export.</li>
            </ul>
        </section>

        <section aria-labelledby="security">
            <h2 id="security" class="text-xl font-semibold tracking-tight">5. Security</h2>
            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                Credentials are stored using one-way hashing, sessions are protected against fixation
                and cross-site request forgery, and digital gate passes are signed server-side with a
                key that is never sent to your device. Access to security records is restricted by role.
            </p>
        </section>

        <section aria-labelledby="retention">
            <h2 id="retention" class="text-xl font-semibold tracking-tight">6. Data Retention</h2>
            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                Access and visitor records are retained for as long as the community requires them for
                security and audit purposes. Deactivating your account revokes your gate pass immediately;
                associated security records are retained where a legitimate community interest applies.
            </p>
        </section>

        <section aria-labelledby="contact">
            <h2 id="contact" class="text-xl font-semibold tracking-tight">7. Contact</h2>
            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                Questions about this policy, or about the data held on you, should be directed to your
                community administration office.
            </p>
        </section>
    </div>
</article>
@endsection
