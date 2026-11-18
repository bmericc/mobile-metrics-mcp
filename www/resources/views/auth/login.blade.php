@php($providers = \App\Http\Controllers\Auth\SocialLoginController::enabledProviders())
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş - Mobile Metrics MCP</title>
    @include('partials.brand-head')
    {{-- Vite build'e bağımlı olmasın diye stiller inline. --}}
    <style>
        :root { color-scheme: light dark; --bg: #ffffff; --fg: #18181b; --muted: #71717a; --border: #e4e4e7; --btn: #ffffff; --btn-hover: #f4f4f5; --accent: #4f46e5; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b0b0f; --fg: #fafafa; --muted: #a1a1aa; --border: #27272a; --btn: #16161c; --btn-hover: #1f1f27; --accent: #818cf8; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--fg); font: 15px/1.55 ui-sans-serif, system-ui, -apple-system, sans-serif; }
        a { color: var(--accent); }
        .page { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); }

        .brand { position: relative; overflow: hidden; color: #fff; padding: 48px 56px; display: flex; flex-direction: column; justify-content: space-between;
            background: radial-gradient(1200px 600px at -10% -10%, #6366f1 0%, transparent 60%), radial-gradient(900px 600px at 110% 110%, #7c3aed 0%, transparent 55%), #1e1b4b; }
        .brand .logo { display: inline-flex; align-items: center; gap: 6px; color: #fff; text-decoration: none; font-weight: 700; font-size: 20px; }
        .brand h2 { font-size: clamp(28px, 3.2vw, 40px); line-height: 1.15; letter-spacing: -0.02em; margin: 0 0 16px; max-width: 520px; }
        .brand p.lead { color: rgba(255,255,255,.75); font-size: 17px; margin: 0 0 32px; max-width: 480px; }
        .brand ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 14px; max-width: 480px; }
        .brand li { display: flex; gap: 12px; align-items: flex-start; color: rgba(255,255,255,.9); }
        .brand li svg { flex: none; margin-top: 3px; }
        .brand .stores { display: flex; flex-wrap: wrap; gap: 8px; }
        .brand .tag { font-size: 13px; padding: 4px 12px; border-radius: 999px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); }

        .form { display: flex; align-items: center; justify-content: center; padding: 48px 24px; }
        .form .inner { width: 100%; max-width: 380px; }
        .form .mobile-logo { display: none; }
        .form h1 { font-size: 26px; letter-spacing: -0.01em; margin: 0 0 6px; }
        .form .sub { color: var(--muted); margin: 0 0 28px; }
        .providers { display: grid; gap: 12px; }
        .provider { display: flex; align-items: center; gap: 12px; width: 100%; padding: 13px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--btn); color: var(--fg); font: inherit; font-weight: 600; text-decoration: none; transition: background .15s, border-color .15s; }
        .provider:hover { background: var(--btn-hover); border-color: var(--muted); }
        .provider svg { flex: none; }
        .provider span { flex: 1; text-align: center; margin-right: 22px; }
        .note { margin: 24px 0 0; padding: 12px 14px; border-radius: 10px; background: var(--btn-hover); color: var(--muted); font-size: 13px; }
        .legal { margin-top: 28px; font-size: 13px; color: var(--muted); }
        .legal a { color: inherit; }

        @media (max-width: 900px) {
            .page { grid-template-columns: 1fr; }
            .brand { display: none; }
            .form { align-items: flex-start; padding-top: 56px; }
            .form .mobile-logo { display: flex; align-items: center; gap: 6px; margin-bottom: 32px; color: var(--fg); text-decoration: none; font-weight: 700; font-size: 18px; }
        }
    </style>
</head>
<body>
<div class="page">
    <aside class="brand">
        <a class="logo" href="{{ route('home') }}" aria-label="Mobile Metrics MCP">
            @include('partials.brand-logo', ['height' => 44, 'variant' => 'white'])
        </a>

        <div>
            <h2>Uygulama istatistikleriniz, tek yerde.</h2>
            <p class="lead">Tüm geliştirici hesaplarınızdan verileri her gün otomatik toplayın, yapay zekâ asistanınıza sorun.</p>
            <ul>
                @foreach ([
                    'İndirme, kurulum, kaldırma, puan ve çökme oranları',
                    'Tüm mağazalardaki kullanıcı yorumları tek listede',
                    'Claude gibi asistanlarla MCP üzerinden doğal dille analiz',
                    'Mağaza hesaplarına yalnızca okuma erişimi, şifreli saklama',
                ] as $item)
                    <li>
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="rgba(255,255,255,.18)"/><path d="M6 10.2l2.6 2.6L14 7.4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="stores">
            <span class="tag">Google Play</span><span class="tag">App Store</span><span class="tag">AppGallery</span>
        </div>
    </aside>

    <main class="form">
        <div class="inner">
            <a class="mobile-logo" href="{{ route('home') }}" aria-label="Mobile Metrics MCP">
                @include('partials.brand-logo', ['height' => 38])
            </a>

            <h1>Giriş yap</h1>
            <p class="sub">Hesabınla devam et. İlk girişte hesabın otomatik oluşturulur.</p>

            <div class="providers">
                @forelse ($providers as $provider)
                    <a class="provider" href="{{ route('social.redirect', $provider) }}">
                        @include('auth.provider-icon', ['provider' => $provider])
                        <span>{{ \App\Http\Controllers\Auth\SocialLoginController::LABELS[$provider] }} ile devam et</span>
                    </a>
                @empty
                    <p class="note">Henüz bir giriş sağlayıcısı yapılandırılmadı.</p>
                @endforelse
            </div>

            <p class="note">Aynı e-posta adresini kullanan Google, Apple ve Huawei hesapların tek hesapta birleşir.</p>

            <p class="legal">
                Devam ederek <a href="{{ route('legal.terms') }}">Kullanım Koşulları</a>'nı ve
                <a href="{{ route('legal.privacy') }}">Gizlilik Politikası</a>'nı kabul etmiş olursun.
                · <a href="{{ route('home') }}">Mobile Metrics MCP hakkında</a>
            </p>
        </div>
    </main>
</div>
</body>
</html>
