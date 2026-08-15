<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#206bc4">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    @php $sysLogo = \App\Models\SystemSetting::publicUrl(\App\Models\SystemSetting::get('app_logo')); @endphp
    <link rel="manifest" href="/pwa-manifest.json">
    <link rel="icon" href="{{ $sysLogo ?: '/icons/icon.svg' }}">
    <link rel="apple-touch-icon" href="{{ $sysLogo ?: '/icons/icon.svg' }}">
    <title>@yield('title', 'Dashboard') · {{ \App\Models\SystemSetting::get('app_name', config('app.name')) }}</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.5.0/dist/tabler-icons.min.css">
    <style>
        body { font-feature-settings: "cv02","cv03","cv04","cv11"; }
        .page-pretitle { letter-spacing: .04em; }
        .stat-card .h1 { font-weight: 700; }

        /* ── Mobile bottom navigation ───────────────────────────── */
        .bottom-nav {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            display: flex;
            justify-content: space-around;
            align-items: stretch;
            background: #fff;
            border-top: 1px solid rgba(0,0,0,.08);
            box-shadow: 0 -4px 12px rgba(0,0,0,.06);
            z-index: 1030;
            padding-bottom: env(safe-area-inset-bottom);
        }
        .bottom-nav-item {
            flex: 1 1 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 4px 6px;
            color: #6c757d;
            text-decoration: none;
            font-size: 11px;
            line-height: 1.1;
            gap: 2px;
            min-height: 56px;
        }
        .bottom-nav-item i { font-size: 22px; }
        .bottom-nav-item.active { color: var(--tblr-primary, #206bc4); font-weight: 600; }
        .bottom-nav-item:active { background: rgba(0,0,0,.04); }

        /* Reserve space so page content + footer aren't hidden by the bar. */
        @media (max-width: 991.98px) {
            body { padding-bottom: calc(60px + env(safe-area-inset-bottom)); }
            .container-xl { padding-left: .75rem; padding-right: .75rem; }
            .page-header .page-title { font-size: 1.15rem; }

            /* Make wide tables horizontally scroll instead of overflow. */
            .card > .table-responsive,
            .card-table { -webkit-overflow-scrolling: touch; }
            .card-body > .table:not(.table-responsive) { display: block; overflow-x: auto; }

            /* Mobile-only "View more" collapse on long tables — when the
               table has the .table-collapsible class (added by JS below
               only when there are more than 5 body rows), every row past
               the 5th is hidden until the user clicks the toggle. */
            .table-collapsible.is-collapsed tbody tr.table-row-hidden { display: none; }
        }
        .table-more-toggle {
            display: block;
            width: 100%;
            border: 0;
            background: transparent;
            color: var(--tblr-primary, #206bc4);
            padding: .65rem .75rem;
            font-weight: 600;
            border-top: 1px solid var(--tblr-border-color, #e6e7e9);
        }
        .table-more-toggle:hover { background: rgba(32,107,196,.06); }
    </style>
    @stack('head')
</head>
<body>
<div class="page">
    @include('partials.sidebar')
    <div class="page-wrapper">
        @include('partials.topbar')

        <div class="page-body">
            <div class="container-xl">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        {{ session('status') }}
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        {{ session('error') }}
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                @if(session('credentials'))
<div class="container-xl mt-3"><div class="alert alert-success alert-dismissible">
    <i class="ti ti-key me-1"></i><strong>{{ session('credentials') }}</strong>
    <small class="d-block mt-1 text-muted">Save this password — it will not be shown again.</small>
    <a class="btn-close" data-bs-dismiss="alert"></a>
</div></div>
@endif
@yield('content')
            </div>
        </div>

        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl">
                <div class="row text-muted small align-items-center">
                    <div class="col">© {{ date('Y') }} {{ config('app.name') }}</div>
                    <div class="col-auto">VSLA Manager · v1.0 · {{ __('Built by') }} <strong>Success Path Ltd</strong></div>
                </div>
            </div>
        </footer>
    </div>
</div>

@include('partials.bottom_nav')

<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function (error) {
            console.warn('PWA service worker registration failed:', error);
        });
    });
}

/**
 * Mobile-only "View more" toggle for long tables.
 *
 * On viewports under 768px, every table whose body has more than 5 rows
 * is collapsed to its first 5 rows; a "View more (N more)" toggle is
 * appended right below the table. Clicking it reveals the rest and
 * switches to "Show less". On wider screens the toggle is removed and
 * every row is shown again, so resizing/rotating the device just works.
 */
(function () {
    const LIMIT = 5;
    const MOBILE_MAX = 767;

    function getRows(tbl) {
        const tbody = tbl.tBodies[0];
        return tbody ? Array.from(tbody.rows) : [];
    }

    function setupTable(tbl) {
        const rows = getRows(tbl);
        if (rows.length <= LIMIT) return;
        if (tbl.dataset.collapsibleReady === '1') return;
        tbl.dataset.collapsibleReady = '1';
        tbl.classList.add('table-collapsible');
        rows.forEach((r, i) => { if (i >= LIMIT) r.classList.add('table-row-hidden'); });

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'table-more-toggle d-none d-print-none';
        const hidden = rows.length - LIMIT;
        btn.textContent = 'View all (' + hidden + ' more)';
        btn.addEventListener('click', function () {
            const collapsed = tbl.classList.toggle('is-collapsed');
            btn.textContent = collapsed
                ? 'View all (' + hidden + ' more)'
                : 'Show less';
        });

        // Insert directly after the table (or its scroll wrapper).
        const wrapper = tbl.closest('.table-responsive') || tbl;
        wrapper.parentNode.insertBefore(btn, wrapper.nextSibling);
        // Keep a direct reference so we don't need DOM queries later.
        tbl._moreToggleBtn = btn;
    }

    function applyForViewport() {
        const isMobile = window.innerWidth <= MOBILE_MAX;
        document.querySelectorAll('table.table').forEach(function (tbl) {
            if (isMobile) {
                setupTable(tbl);
                if (tbl.classList.contains('table-collapsible')) {
                    tbl.classList.add('is-collapsed');
                    if (tbl._moreToggleBtn) {
                        tbl._moreToggleBtn.classList.remove('d-none');
                        tbl._moreToggleBtn.textContent =
                            'View all (' + (getRows(tbl).length - LIMIT) + ' more)';
                    }
                }
            } else if (tbl.classList.contains('table-collapsible')) {
                tbl.classList.remove('is-collapsed');
                if (tbl._moreToggleBtn) tbl._moreToggleBtn.classList.add('d-none');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', applyForViewport);

    let resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applyForViewport, 150);
    });
})();
</script>

@stack('scripts')
</body>
</html>
