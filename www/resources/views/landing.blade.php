<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kalehost Metrics — Uygulama istatistikleriniz tek yerde</title>
    <meta name="description" content="Google Play, App Store ve AppGallery uygulama istatistiklerinizi ve yorumlarınızı tek panelde toplayın, yapay zekâ asistanınızla analiz edin.">
    <style>
        :root { color-scheme: light dark; --bg: #f4f4f5; --card: #fff; --fg: #18181b; --muted: #71717a; --border: #e4e4e7; --primary: #4f46e5; --primary-fg: #fff; }
        @media (prefers-color-scheme: dark) { :root { --bg: #09090b; --card: #18181b; --fg: #fafafa; --muted: #a1a1aa; --border: #27272a; --primary: #818cf8; --primary-fg: #0b0b12; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.6 ui-sans-serif, system-ui, sans-serif; }
        a { color: var(--primary); }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 0 16px; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; }
        header .brand { font-weight: 700; font-size: 18px; color: var(--fg); text-decoration: none; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 18px; border-radius: 8px; font-weight: 600; text-decoration: none; border: 1px solid var(--border); color: var(--fg); background: var(--card); }
        .btn-primary { background: var(--primary); border-color: var(--primary); color: var(--primary-fg); }
        .hero { padding: 56px 0 40px; max-width: 720px; }
        .hero h1 { font-size: clamp(30px, 5vw, 46px); line-height: 1.15; margin: 0 0 16px; letter-spacing: -0.02em; }
        .hero p { font-size: 18px; color: var(--muted); margin: 0 0 28px; }
        .cta { display: flex; flex-wrap: wrap; gap: 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; padding: 24px 0 48px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 22px; }
        .card h3 { margin: 0 0 8px; font-size: 17px; }
        .card p { margin: 0; color: var(--muted); font-size: 15px; }
        .stores { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .tag { font-size: 13px; padding: 3px 10px; border-radius: 999px; border: 1px solid var(--border); color: var(--muted); }
        .example { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 22px; margin-bottom: 48px; }
        .example h2 { margin: 0 0 12px; font-size: 20px; }
        .example q { display: block; margin: 8px 0; color: var(--muted); font-style: italic; }
        footer { border-top: 1px solid var(--border); padding: 24px 0 40px; color: var(--muted); font-size: 14px; display: flex; flex-wrap: wrap; gap: 8px 20px; justify-content: space-between; }
        footer nav { display: flex; flex-wrap: wrap; gap: 16px; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <a class="brand" href="{{ route('home') }}">Kalehost Metrics</a>
        @auth
            <a class="btn" href="{{ url('/app') }}">Panele git</a>
        @else
            <a class="btn" href="{{ route('login') }}">Giriş yap</a>
        @endauth
    </header>

    <section class="hero">
        <h1>Uygulama istatistikleriniz, tek yerde.</h1>
        <p>
            Google Play, App Store ve AppGallery'deki tüm geliştirici hesaplarınızdan indirme, kurulum, puan ve
            yorumları her gün otomatik toplayın. Sonra yapay zekâ asistanınıza sorun.
        </p>
        <div class="cta">
            @auth
                <a class="btn btn-primary" href="{{ url('/app') }}">Panele git</a>
            @else
                <a class="btn btn-primary" href="{{ route('login') }}">Ücretsiz başla</a>
            @endauth
            <a class="btn" href="#nasil">Nasıl çalışır?</a>
        </div>
    </section>

    <section class="grid" id="nasil">
        <div class="card">
            <h3>Üç mağaza, birden fazla hesap</h3>
            <p>Şirket, müşteri veya kişisel; istediğiniz kadar geliştirici hesabını tek çalışma alanında toplayın.</p>
            <div class="stores"><span class="tag">Google Play</span><span class="tag">App Store</span><span class="tag">AppGallery</span></div>
        </div>
        <div class="card">
            <h3>Anahtar yapıştırmadan bağlanın</h3>
            <p>Google Play'i tek tıkla bağlayın; App Store ve AppGallery için adım adım rehberle anahtar dosyasını sürükleyip bırakın.</p>
        </div>
        <div class="card">
            <h3>Her gün güncel</h3>
            <p>Kurulum, kaldırma, sayfa görüntüleme, puan, çökme oranları ve kullanıcı yorumları her gün otomatik çekilir.</p>
        </div>
        <div class="card">
            <h3>Yapay zekâ ile analiz</h3>
            <p>Claude gibi asistanları MCP ile bağlayın; verilerinizi doğal dille sorgulayın, karşılaştırın, yorumları özetletin.</p>
        </div>
        <div class="card">
            <h3>Yalnızca okuma, şifreli saklama</h3>
            <p>Mağaza hesaplarınıza sadece okumak için erişilir; anahtarlar şifreli saklanır ve tekrar gösterilmez.</p>
        </div>
    </section>

    <section class="example">
        <h2>Asistanınıza sorabilecekleriniz</h2>
        <q>Radyo C geçen ay hangi mağazada kaç yeni kurulum aldı?</q>
        <q>Son iki haftada 1-2 yıldızlı yorumlarda en çok hangi sorundan bahsediliyor?</q>
        <q>Bu ay en çok indirme gelen 10 ülke hangileri, geçen aya göre değişim ne?</q>
    </section>

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
