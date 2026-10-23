{{-- Filament'in derlenmiş CSS'i yalnızca kendi kullandığı sınıfları içerdiği için tablolar inline stilli. --}}
<style>
    .kr-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .kr-table th { text-align: left; font-weight: 600; color: var(--gray-500); padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    .kr-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
    .kr-table .num { text-align: right; font-variant-numeric: tabular-nums; }
    .kr-table tfoot td { font-weight: 700; border-bottom: none; }
    .dark .kr-table th { border-color: rgba(255,255,255,.1); color: var(--gray-400); }
    .dark .kr-table td { border-color: rgba(255,255,255,.05); }
    .kr-dot { display: inline-block; width: .6rem; height: .6rem; border-radius: 999px; margin-right: .4rem; vertical-align: middle; }
    .kr-muted { color: var(--gray-500); font-size: .8rem; }
    .kr-bar { height: .4rem; border-radius: 999px; background: var(--primary-500); opacity: .7; }
    .kr-scroll { overflow-x: auto; }
    .kr-empty { color: var(--gray-500); font-size: .875rem; padding: .5rem 0; }
    .kr-stars { color: #f59e0b; letter-spacing: 1px; }
</style>
