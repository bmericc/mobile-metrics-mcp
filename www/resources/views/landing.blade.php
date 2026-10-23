<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kalehost Metrics — Uygulama istatistikleriniz tek yerde</title>
    <meta name="description" content="Google Play, App Store ve AppGallery uygulama istatistiklerinizi ve yorumlarınızı tek panelde toplayın, yapay zekâ asistanınızla analiz edin.">
    @include('partials.brand-head')
    {{-- Giriş sayfasıyla aynı mor marka zemini; Vite build'e bağımlı olmasın diye stiller inline. --}}
    <style>
        :root { color-scheme: light dark; --bg: #ffffff; --soft: #f5f5fa; --fg: #18181b; --muted: #6b6b7b; --border: #e6e6ef; --accent: #4f46e5; --ink: #1e1b4b; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b0b12; --soft: #12121c; --fg: #f4f4f8; --muted: #a1a1b5; --border: #24243a; --accent: #a5b4fc; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, sans-serif; }
        a { color: inherit; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 16px; }

        .hero { position: relative; overflow: hidden; color: #fff;
            background: radial-gradient(1200px 640px at -10% -20%, #6366f1 0%, transparent 60%), radial-gradient(900px 600px at 110% 120%, #7c3aed 0%, transparent 55%), var(--ink); }
        .hero::after { content: ""; position: absolute; inset: 0; pointer-events: none;
            background-image: linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 56px 56px; mask-image: linear-gradient(180deg, #000 0%, transparent 85%); }
        .hero > * { position: relative; z-index: 1; }
        nav.top { display: flex; justify-content: space-between; align-items: center; padding: 24px 0; gap: 16px; }
        nav.top .links { display: flex; align-items: center; gap: 24px; font-size: 15px; }
        nav.top .links a { text-decoration: none; color: rgba(255,255,255,.8); }
        nav.top .links a:hover { color: #fff; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; transition: transform .15s, background .15s; }
        .btn:hover { transform: translateY(-1px); }
        .btn-light { background: #fff; color: var(--ink) !important; }
        .btn-ghost { border: 1px solid rgba(255,255,255,.3); color: #fff !important; }
        .btn-ghost:hover { background: rgba(255,255,255,.08); }
        .btn-sm { padding: 8px 14px; font-size: 14px; }

        .hero-grid { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr); gap: 56px; align-items: center; padding: 48px 0 96px; }
        .eyebrow { display: inline-flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }
        .tag { font-size: 13px; padding: 4px 12px; border-radius: 999px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); }
        .hero h1 { font-size: clamp(36px, 5.4vw, 60px); line-height: 1.04; letter-spacing: -0.035em; margin: 0 0 20px; font-weight: 800; }
        .hero h1 em { font-style: normal; background: linear-gradient(90deg, #c7d2fe, #f0abfc); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hero .lead { color: rgba(255,255,255,.75); font-size: 18px; margin: 0 0 32px; max-width: 520px; }
        .cta { display: flex; flex-wrap: wrap; gap: 12px; }

        /* Asistan sohbeti: ürünün asıl işini gösteren örnek. */
        .chat { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.16); border-radius: 18px; padding: 20px; backdrop-filter: blur(8px); box-shadow: 0 30px 80px -20px rgba(0,0,0,.5); }
        .chat .bar { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: rgba(255,255,255,.55); margin-bottom: 16px; }
        .chat .bar span:first-child::before { content: ""; display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #4ade80; margin-right: 8px; }
        .bubble { border-radius: 14px; padding: 12px 14px; font-size: 15px; margin-bottom: 12px; }
        .bubble.user { background: #fff; color: var(--ink); margin-left: 15%; border-bottom-right-radius: 4px; }
        .bubble.bot { background: rgba(15,12,48,.55); border: 1px solid rgba(255,255,255,.1); margin-right: 6%; border-bottom-left-radius: 4px; }
        .bubble.bot p { margin: 0 0 12px; color: rgba(255,255,255,.88); }
        .tool { font: 12px ui-monospace, SFMono-Regular, Menlo, monospace; color: #c7d2fe; margin-bottom: 10px; }
        .bars { display: grid; gap: 8px; }
        .bars div { display: grid; grid-template-columns: 92px 1fr 44px; align-items: center; gap: 10px; font-size: 13px; color: rgba(255,255,255,.8); }
        .bars i { display: block; height: 10px; border-radius: 6px; background: linear-gradient(90deg, #818cf8, #c084fc); }
        .bars b { text-align: right; font-weight: 600; color: #fff; }
        .chat small { display: block; margin-top: 4px; color: rgba(255,255,255,.4); font-size: 12px; }

        section.band { padding: 88px 0; }
        section.band.soft { background: var(--soft); }
        .kicker { color: var(--accent); font-weight: 700; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; margin: 0 0 12px; }
        h2 { font-size: clamp(28px, 3.6vw, 40px); line-height: 1.12; letter-spacing: -0.025em; margin: 0 0 16px; }
        .sub { color: var(--muted); font-size: 17px; max-width: 600px; margin: 0; }

        .steps { list-style: none; padding: 0; margin: 48px 0 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; counter-reset: step; }
        .steps li { counter-increment: step; border-top: 2px solid var(--border); padding-top: 24px; position: relative; }
        .steps li::before { content: "0" counter(step); position: absolute; top: -2px; left: 0; width: 48px; border-top: 2px solid var(--accent); padding-top: 22px; font-weight: 800; font-size: 14px; color: var(--accent); }
        .steps h3 { margin: 28px 0 8px; font-size: 20px; letter-spacing: -0.01em; }
        .steps p { margin: 0; color: var(--muted); }

        .split { display: grid; grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr); gap: 56px; align-items: start; }
        table.matrix { width: 100%; border-collapse: collapse; font-size: 15px; background: var(--bg); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
        .matrix th, .matrix td { padding: 13px 16px; border-bottom: 1px solid var(--border); text-align: center; }
        .matrix th { font-size: 13px; color: var(--muted); font-weight: 600; }
        .matrix th:first-child, .matrix td:first-child { text-align: left; }
        .matrix tr:last-child td { border-bottom: 0; }
        .yes { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: var(--accent); }
        .no { color: var(--muted); opacity: .6; }
        .note { color: var(--muted); font-size: 13px; margin-top: 12px; }

        .mcp { color: #fff; background: radial-gradient(800px 400px at 100% 0%, #7c3aed 0%, transparent 60%), var(--ink); }
        .mcp .kicker { color: #c7d2fe; }
        .mcp .sub { color: rgba(255,255,255,.72); }
        .endpoint { display: inline-flex; align-items: center; gap: 12px; margin: 28px 0 0; padding: 12px 16px; border-radius: 12px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16); font: 15px ui-monospace, SFMono-Regular, Menlo, monospace; word-break: break-all; }
        .endpoint span { color: rgba(255,255,255,.5); font-family: ui-sans-serif, system-ui, sans-serif; font-size: 13px; }
        .questions { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
        .questions li { padding: 16px 18px; border-radius: 12px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); color: rgba(255,255,255,.9); }
        .questions li::before { content: "› "; color: #c7d2fe; font-weight: 700; }
        .trust { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 64px; padding-top: 32px; border-top: 1px solid rgba(255,255,255,.14); }
        .trust b { display: block; margin-bottom: 4px; }
        .trust p { margin: 0; color: rgba(255,255,255,.65); font-size: 15px; }

        footer { padding: 32px 0 48px; color: var(--muted); font-size: 14px; display: flex; flex-wrap: wrap; gap: 12px 24px; justify-content: space-between; align-items: center; }
        footer nav { display: flex; flex-wrap: wrap; gap: 16px; }
        footer a { text-decoration: none; }
        footer a:hover { color: var(--fg); }

        @media (max-width: 900px) {
            .hero-grid, .split { grid-template-columns: 1fr; gap: 40px; }
            .hero-grid { padding: 24px 0 64px; }
            .steps, .trust { grid-template-columns: 1fr; gap: 32px; }
            section.band { padding: 64px 0; }
            nav.top .links a.anchor { display: none; }
        }
        @media (max-width: 420px) {
            .matrix th, .matrix td { padding: 11px 6px; }
            .matrix th { font-size: 11px; }
            .bars div { grid-template-columns: 78px 1fr 36px; }
        }
    </style>
</head>
<body>
<header class="hero">
    <div class="wrap">
        <nav class="top">
            <a href="{{ route('home') }}" aria-label="Kalehost Metrics">
                @include('partials.brand-logo', ['height' => 38, 'variant' => 'white'])
            </a>
            <div class="links">
                <a class="anchor" href="#nasil">Nasıl çalışır</a>
                <a class="anchor" href="#mcp">Yapay zekâ</a>
                @auth
                    <a class="btn btn-light btn-sm" href="{{ url('/app') }}">Panele git</a>
                @else
                    <a class="btn btn-light btn-sm" href="{{ route('login') }}">Giriş yap</a>
                @endauth
            </div>
        </nav>

        <div class="hero-grid">
            <div>
                <div class="eyebrow"><span class="tag">Google Play</span><span class="tag">App Store</span><span class="tag">AppGallery</span></div>
                <h1>Uygulama istatistikleriniz, <em>tek yerde.</em></h1>
                <p class="lead">Tüm geliştirici hesaplarınızdan kurulum, kaldırma, çökme ve yorumları her gün otomatik toplayın. Sonra asistanınıza düz Türkçe ile sorun.</p>
                <div class="cta">
                    @auth
                        <a class="btn btn-light" href="{{ url('/app') }}">Panele git</a>
                    @else
                        <a class="btn btn-light" href="{{ route('login') }}">Hemen başla</a>
                    @endauth
                    <a class="btn btn-ghost" href="#nasil">Nasıl çalışır?</a>
                </div>
            </div>

            <div class="chat" aria-label="Örnek asistan sohbeti">
                <div class="bar"><span>Claude · Kalehost Metrics bağlı</span><span>örnek</span></div>
                <div class="bubble user">Radyo C geçen ay hangi mağazada kaç yeni kurulum aldı?</div>
                <div class="bubble bot">
                    <div class="tool">get-metrics · Eylül 2026 · installs</div>
                    <p>Toplam 577 kurulum. Büyük kısmı Google Play'den geldi; AppGallery bir önceki aya göre iki katına çıktı.</p>
                    <div class="bars">
                        <div><span>Google Play</span><i style="width:100%"></i><b>412</b></div>
                        <div><span>App Store</span><i style="width:31%"></i><b>128</b></div>
                        <div><span>AppGallery</span><i style="width:9%"></i><b>37</b></div>
                    </div>
                </div>
                <small>Rakamlar örnek amaçlıdır.</small>
            </div>
        </div>
    </div>
</header>

<main>
    <section class="band" id="nasil">
        <div class="wrap">
            <p class="kicker">Nasıl çalışır</p>
            <h2>Bir kez bağlayın, gerisi her gün kendiliğinden.</h2>
            <p class="sub">Şirket, müşteri ya da kişisel; istediğiniz kadar geliştirici hesabını tek çalışma alanında toplayın.</p>
            <ol class="steps">
                <li>
                    <h3>Hesaplarınızı bağlayın</h3>
                    <p>Google Play'i Google hesabınızla tek tıkla bağlayın. App Store ve AppGallery için rehber, anahtar dosyasını nereden alacağınızı adım adım gösterir.</p>
                </li>
                <li>
                    <h3>Veriler her sabah toplanır</h3>
                    <p>Mağazaların raporları her gün çekilir, son günler düzeltmelere karşı yeniden okunur. Uygulamaları ürün altında birleştirip mağazaları yan yana görün.</p>
                </li>
                <li>
                    <h3>Asistanınıza sorun</h3>
                    <p>Claude gibi bir asistanı MCP ile bağlayın; karşılaştırmayı, yorum özetini ve grafiği o hazırlasın.</p>
                </li>
            </ol>
        </div>
    </section>

    <section class="band soft">
        <div class="wrap split">
            <div>
                <p class="kicker">Toplanan veriler</p>
                <h2>Her mağazanın verdiği her şey, aynı adlarla.</h2>
                <p class="sub">Mağazalar aynı şeye farklı ad veriyor. Hepsi ortak metriklere çevrilir; ülke kırılımıyla birlikte saklanır.</p>
            </div>
            <div>
                <table class="matrix">
                    <thead><tr><th>Metrik</th><th>Google Play</th><th>App Store</th><th>AppGallery</th></tr></thead>
                    <tbody>
                        @foreach ([
                            ['Kurulum / indirme', 1, 1, 1],
                            ['Güncelleme', 1, 1, 1],
                            ['Kaldırma', 1, 1, 1],
                            ['Gösterim', 0, 1, 1],
                            ['Çökme', 1, 1, 0],
                            ['Puan', 1, 0, 0],
                            ['Kullanıcı yorumları', 1, 1, 1],
                        ] as [$label, $play, $apple, $huawei])
                            <tr>
                                <td>{{ $label }}</td>
                                @foreach ([$play, $apple, $huawei] as $has)
                                    <td>@if ($has)<span class="yes" aria-label="var"></span>@else<span class="no" aria-label="yok">–</span>@endif</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="note">App Store'da kaldırma, gösterim ve çökme analitik raporla gelir; o açık değilse indirme ve güncelleme satış raporundan alınır.</p>
            </div>
        </div>
    </section>

    <section class="band mcp" id="mcp">
        <div class="wrap">
            <div class="split">
                <div>
                    <p class="kicker">Yapay zekâ ile analiz</p>
                    <h2>Panelde aramayın, sorun.</h2>
                    <p class="sub">Kalehost Metrics bir MCP sunucusu sunar. Asistanınıza bağlayın; girişi kendi hesabınızla yaparsınız, asistan yalnızca sizin çalışma alanınızı görür.</p>
                    <div class="endpoint"><span>MCP adresi</span>{{ url('/mcp') }}</div>
                </div>
                <ul class="questions">
                    <li>Son iki haftada 1-2 yıldızlı yorumlarda en çok hangi sorundan bahsediliyor?</li>
                    <li>Bu ay en çok indirme gelen 10 ülke hangileri, geçen aya göre değişim ne?</li>
                    <li>Güvendeyim'in Android ve iOS kurulumlarını son 90 gün için tek grafikte göster.</li>
                    <li>Hangi uygulamada kaldırma oranı son ayda arttı?</li>
                </ul>
            </div>
            <div class="trust">
                <div><b>Yalnızca okuma</b><p>Mağaza hesaplarınıza sadece rapor okumak için erişilir; hiçbir şey yayınlanmaz veya değiştirilmez.</p></div>
                <div><b>Şifreli saklama</b><p>Anahtarlar ve bağlantılar şifreli saklanır, panelde bir daha gösterilmez.</p></div>
                <div><b>Admin yetkisi gerekmez</b><p>Dar yetkili anahtarlar yeterli; Google, Apple ve Huawei ile tek tıkla giriş.</p></div>
            </div>
        </div>
    </section>
</main>

<div class="wrap">
    <footer>
        <span>© {{ date('Y') }} Kale Host · Bahri CANLI</span>
        <nav>
            <a href="{{ route('legal.privacy') }}">Gizlilik</a>
            <a href="{{ route('legal.terms') }}">Kullanım Koşulları</a>
            <a href="{{ route('legal.privacy.en') }}">Privacy (EN)</a>
            <a href="{{ route('legal.terms.en') }}">Terms (EN)</a>
            <a href="mailto:bmericc@gmail.com">İletişim</a>
        </nav>
    </footer>
</div>
</body>
</html>
