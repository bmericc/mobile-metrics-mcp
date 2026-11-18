<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    @include('partials.brand-head')
    {{-- Vite build'e bağımlı olmasın diye stiller inline. --}}
    <style>
        :root { color-scheme: light dark; --bg: #f4f4f5; --card: #fff; --fg: #18181b; --muted: #71717a; --border: #e4e4e7; --primary: #4f46e5; }
        @media (prefers-color-scheme: dark) { :root { --bg: #09090b; --card: #18181b; --fg: #fafafa; --muted: #a1a1aa; --border: #27272a; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--fg); font: 15px/1.5 ui-sans-serif, system-ui, sans-serif; }
        .shell { width: 100%; max-width: 400px; }
        .shell > footer { margin-top: 16px; text-align: center; font-size: 13px; color: var(--muted); }
        .shell > footer a { color: inherit; }
        .card { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 32px; }
        h1 { font-size: 22px; margin: 0 0 4px; text-align: center; }
        p.muted { color: var(--muted); margin: 0 0 24px; text-align: center; }
        .stack { display: grid; gap: 10px; }
        .btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border); background: var(--card); color: var(--fg); font: inherit; font-weight: 500; text-decoration: none; cursor: pointer; }
        .btn:hover { border-color: var(--muted); }
        .btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .box { border: 1px solid var(--border); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
        .row { display: flex; gap: 10px; }
        .row form { flex: 1; }
    </style>
</head>
<body>
<div class="shell">
<main class="card">
    <div style="display:flex;justify-content:center;margin-bottom:16px">
        <a href="{{ route('home') }}" aria-label="Mobile Metrics MCP">@include('partials.brand-logo', ['height' => 44])</a>
    </div>
    @yield('content')
</main>
@hasSection('footer')
    <footer>@yield('footer')</footer>
@endif
</div>
</body>
</html>
