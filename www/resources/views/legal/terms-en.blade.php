@extends('layouts.legal')

@section('title', 'Terms of Use')
@section('alternate', route('legal.terms'))

@section('content')
    <h1>Terms of Use</h1>
    <p class="muted">Last updated: October 23, 2026</p>

    <p>
        These terms govern the use of <strong>Mobile Metrics MCP</strong>
        (<a href="https://metrics.kalehost.net">metrics.kalehost.net</a>), provided by Bahri CANLI under the Kale Host
        brand. By signing in you accept these terms and the <a href="{{ route('legal.privacy.en') }}">Privacy Policy</a>.
    </p>

    <h2>1. The service</h2>
    <p>
        Mobile Metrics MCP reads app statistics and reviews from your developer accounts on Google Play, the App Store and
        AppGallery and shows them in a dashboard and through the AI assistants you connect (MCP). The service accesses
        your store accounts for reading only.
    </p>

    <h2>2. Account and authority</h2>
    <ul>
        <li>You sign in with your Google, Apple or Huawei account and are responsible for keeping it secure.</li>
        <li>You may only connect developer accounts you are authorised to access. If you connect an account on behalf of a company or team, you confirm that you are authorised to do so.</li>
        <li>The terms and API policies of the stores you connect (Google, Apple, Huawei) also apply.</li>
    </ul>

    <h2>3. Acceptable use</h2>
    <p>You may not use the service for unlawful purposes, to access other people's accounts without authorisation, or in a way that harms its infrastructure (e.g. excessive automated requests, probing for vulnerabilities).</p>

    <h2>4. Your data</h2>
    <p>
        The data fetched from the accounts you connect belongs to you. We process it only to provide the service to you;
        see the <a href="{{ route('legal.privacy.en') }}">Privacy Policy</a> for details.
    </p>

    <h2>5. Accuracy and availability</h2>
    <p>
        Statistics come from reports published by the stores; stores may publish data late, correct it later, or not
        provide it at all for low-volume apps. The service is provided "as is", without any guarantee that data is
        complete, accurate or uninterrupted. Rely on the store's own console for important decisions.
    </p>

    <h2>6. Limitation of liability</h2>
    <p>
        To the extent permitted by applicable law, we are not liable for indirect damages, loss of data or loss of profit
        arising from the use of or inability to use the service.
    </p>

    <h2>7. Termination</h2>
    <p>
        You can remove the store accounts you connected from the dashboard at any time and request deletion of your
        account at <a href="mailto:bmericc@gmail.com">bmericc@gmail.com</a>. Accounts that violate these terms may be
        suspended or closed.
    </p>

    <h2>8. Changes</h2>
    <p>If these terms change, the date on this page is updated; continuing to use the service after a change means you accept the new terms.</p>

    <h2>9. Governing law</h2>
    <p>These terms are governed by the laws of the Republic of Türkiye.</p>

    <h2>10. Contact</h2>
    <p><a href="mailto:bmericc@gmail.com">bmericc@gmail.com</a></p>
@endsection
