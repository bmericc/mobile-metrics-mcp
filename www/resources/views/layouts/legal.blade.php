@php($en = ($lang ?? 'tr') === 'en')
<!DOCTYPE html>
<html lang="{{ $en ? 'en' : 'tr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - Kalehost Metrics</title>
    <style>
        :root { color-scheme: light dark; --bg: #f4f4f5; --card: #fff; --fg: #18181b; --muted: #71717a; --border: #e4e4e7; --primary: #4f46e5; }
        @media (prefers-color-scheme: dark) { :root { --bg: #09090b; --card: #18181b; --fg: #fafafa; --muted: #a1a1aa; --border: #27272a; --primary: #818cf8; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 15px/1.65 ui-sans-serif, system-ui, sans-serif; }
        main { max-width: 760px; margin: 0 auto; padding: 32px 16px 64px; }
        nav { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        nav .links { display: flex; gap: 16px; }
        a { color: var(--primary); }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 32px; }
        h1 { font-size: 26px; margin: 0 0 4px; }
        h2 { font-size: 18px; margin: 28px 0 8px; }
        .muted { color: var(--muted); }
        ul { padding-left: 20px; }
        li { margin: 4px 0; }
        @media (max-width: 480px) { .card { padding: 20px; } }
    </style>
</head>
<body>
<main>
    <nav>
        <a href="{{ route('login') }}"><strong>Kalehost Metrics</strong></a>
        <div class="links">
            <a href="{{ route($en ? 'legal.privacy.en' : 'legal.privacy') }}">{{ $en ? 'Privacy' : 'Gizlilik' }}</a>
            <a href="{{ route($en ? 'legal.terms.en' : 'legal.terms') }}">{{ $en ? 'Terms' : 'Kullanım Koşulları' }}</a>
            <a href="@yield('alternate')" hreflang="{{ $en ? 'tr' : 'en' }}">{{ $en ? 'Türkçe' : 'English' }}</a>
        </div>
    </nav>
    <article class="card">
        @yield('content')
    </article>
</main>
</body>
</html>
