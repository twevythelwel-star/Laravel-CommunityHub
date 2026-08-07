import Link from 'next/link';

export default function PrivacyPolicyPage() {
  return (
    <main className="container mx-auto max-w-3xl px-4 py-12 space-y-8">
      <div>
        <h1 className="text-3xl font-bold font-headline mb-2">Privacy Policy</h1>
        <p className="text-muted-foreground text-sm">Last updated: April 2026</p>
      </div>

      <p className="text-muted-foreground">
        Community Hub (&quot;the App&quot;) is committed to protecting your privacy. This policy explains what data we
        collect, how we use it, and your rights regarding your information.
      </p>

      <section className="space-y-3">
        <h2 className="text-xl font-semibold">1. Data We Collect</h2>
        <ul className="list-disc list-inside space-y-1 text-muted-foreground">
          <li>Account details: name, email address, phone number, and role within the community.</li>
          <li>Community activity: visitor logs, gate pass usage, safety alerts, and billing records.</li>
          <li>Communications: feedback and messages submitted through the app.</li>
          <li>AI-generated content: documents you provide for AI-assisted notification generation.</li>
          <li>Technical data: browser type, device type, and session information.</li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-xl font-semibold">2. How We Use Your Data</h2>
        <ul className="list-disc list-inside space-y-1 text-muted-foreground">
          <li>To provide and improve the Community Hub platform and its features.</li>
          <li>To send community-wide notifications and announcements you have authorised.</li>
          <li>To process billing and HOA dues management.</li>
          <li>To maintain security records, visitor logs, and access control.</li>
          <li>To power AI-assisted features (where you explicitly opt in).</li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-xl font-semibold">3. AI & Third-Party Services</h2>
        <p className="text-muted-foreground">
          Community Hub uses Google Gemini (via the Genkit framework) to power AI-assisted notification generation.
          When you use this feature, the document content you provide is sent to Google&apos;s AI service for processing.
          By using this feature, you consent to this data transfer. Google&apos;s AI services are governed by
          Google&apos;s own privacy policies. We do not use your data to train AI models.
        </p>
      </section>

      <section className="space-y-3">
        <h2 className="text-xl font-semibold">4. Data Sharing</h2>
        <p className="text-muted-foreground">
          We do not sell your personal data. We may share data with:
        </p>
        <ul className="list-disc list-inside space-y-1 text-muted-foreground">
          <li>Google LLC — for AI-assisted features (with your explicit consent).</li>
          <li>Service providers necessary to operate the platform (e.g., hosting, authentication).</li>
          <li>Law enforcement or regulators when required by applicable law.</li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-xl font-semibold">5. Data Retention</h2>
        <p className="text-muted-foreground">
          Your data is retained for as long as your account is active. Upon account deletion, your personal data
          is permanently removed within 30 days. Community records (visitor logs, audit trails) may be retained
          for up to 12 months for security purposes before being deleted.
        </p>
      </section>

      <section className="space-y-3">
        <h2 className="text-xl font-semibold">6. Your Rights</h2>
        <ul className="list-disc list-inside space-y-1 text-muted-foreground">
          <li>Access: You may request a copy of the data we hold about you.</li>
          <li>Correction: You may update your profile information in the Settings page.</li>
          <li>Deletion: You may permanently delete your account via Settings &gt; Deactivation.</li>
          <li>Portability: Contact us to request a data export.</li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-xl font-semibold">7. Contact</h2>
        <p className="text-muted-foreground">
          For privacy-related questions, please contact the system administrator for your community,
          or reach out via the feedback form within the app.
        </p>
      </section>

      <div className="border-t pt-6">
        <Link href="/" className="text-sm text-primary hover:underline">
          ← Back to Login
        </Link>
      </div>
    </main>
  );
}
