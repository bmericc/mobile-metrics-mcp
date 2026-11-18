@extends('layouts.legal')

@section('title', 'Privacy Policy')
@section('alternate', route('legal.privacy'))

@section('content')
    <h1>Privacy Policy</h1>
    <p class="muted">Last updated: October 23, 2026</p>

    <p>
        This policy explains which personal data <strong>Mobile Metrics MCP</strong>
        (<a href="https://metrics.kalehost.net">metrics.kalehost.net</a>) processes and why. Mobile Metrics MCP is a web
        service that lets app developers see the statistics of their apps on Google Play, the App Store and AppGallery
        in one place and analyse them with AI assistants.
    </p>

    <h2>1. Controller</h2>
    <p>
        The controller is Bahri CANLI (individual developer, Kale Host brand, Turkey).
        Contact: <a href="mailto:bmericc@gmail.com">bmericc@gmail.com</a>
    </p>

    <h2>2. Data we process</h2>
    <ul>
        <li><strong>Account data:</strong> the name, e-mail address, profile picture and account ID that Google, Apple or Huawei shares with us when you sign in. We do not store passwords.</li>
        <li><strong>Store connection data:</strong> OAuth refresh tokens for the developer accounts you connect, and the API keys you upload (e.g. an App Store Connect .p8 key, a Huawei Service Account file, a Google service account file).</li>
        <li><strong>Store data:</strong> the app list, daily statistics (downloads, installs, uninstalls, page views, ratings, crash rates) and user reviews of the accounts you connect. Reviews may include the reviewer's nickname and country, as far as the store shares them.</li>
        <li><strong>Technical data:</strong> session cookies, IP address and server logs (for debugging and security).</li>
    </ul>

    <h2>3. Why we process it</h2>
    <ul>
        <li>to create your account and recognise you,</li>
        <li>to regularly fetch statistics and reviews from the store accounts you connect, on your behalf,</li>
        <li>to show this data to you in the dashboard and through the AI assistants you connect (MCP),</li>
        <li>to keep the service secure and running.</li>
    </ul>
    <p>The legal basis is the performance of the service you signed up for and our legitimate interest in keeping it secure.</p>

    <h2>4. Store access is read-only</h2>
    <p>
        Mobile Metrics MCP accesses your store accounts only to read statistics and reviews. It does not publish apps,
        change store settings or reply to reviews on your behalf.
    </p>

    <h2>5. Google user data</h2>
    <p>
        Mobile Metrics MCP' use and transfer to any other app of information received from Google APIs will adhere to the
        <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>,
        including the Limited Use requirements. Google Play data is used only to show you your own statistics; it is not
        used for advertising, not sold, and not read by humans (except for security purposes, to comply with the law, or
        with your explicit consent).
    </p>

    <h2>6. Sharing</h2>
    <p>We do not sell your data and do not use it for advertising. Data reaches third parties only in these cases:</p>
    <ul>
        <li><strong>Stores:</strong> requests are sent to the Google, Apple and Huawei APIs with your authorisation to fetch your data.</li>
        <li><strong>Infrastructure:</strong> the site is served through Cloudflare, so traffic passes through the Cloudflare network. The server runs on Kale Host infrastructure.</li>
        <li><strong>AI assistants you connect:</strong> when you connect an assistant (e.g. Claude) over MCP, the data it asks for is sent to it at your request. Its processing there is governed by that assistant's policy.</li>
        <li><strong>Legal obligations:</strong> lawful requests from competent authorities.</li>
    </ul>

    <h2>7. Retention and security</h2>
    <ul>
        <li>Keys and tokens are stored encrypted in the database and are never shown again in the dashboard.</li>
        <li>Your data is kept until your account is deleted. When you delete a store account in the dashboard, its keys and the data fetched from it are deleted as well.</li>
        <li>Server logs are kept for a short time and cleaned up regularly.</li>
    </ul>

    <h2>8. Your rights</h2>
    <p>
        Under the Turkish Personal Data Protection Law (KVKK) and, where applicable, the GDPR, you have the right to
        access, correct and delete your data and to object to its processing. Write to
        <a href="mailto:bmericc@gmail.com">bmericc@gmail.com</a> for any request. You can also revoke access in your
        <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">Google</a>, Apple and Huawei
        account settings.
    </p>

    <h2>9. Cookies</h2>
    <p>Only essential cookies are used, for your session and security (CSRF protection). There are no analytics or advertising cookies.</p>

    <h2>10. Children</h2>
    <p>The service is intended for app developers and is not directed at people under 18.</p>

    <h2>11. Changes</h2>
    <p>If this policy changes, the date on this page is updated. Significant changes are announced in the dashboard.</p>
@endsection
