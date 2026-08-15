<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1220">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    @php $sysLogo = \App\Models\SystemSetting::publicUrl(\App\Models\SystemSetting::get('app_logo')); @endphp
    <link rel="manifest" href="/pwa-manifest.json">
    <link rel="icon" href="{{ $sysLogo ?: '/icons/icon.svg' }}">
    <link rel="apple-touch-icon" href="{{ $sysLogo ?: '/icons/icon.svg' }}">
    <title>@yield('title', 'Sign in') · {{ \App\Models\SystemSetting::get('app_name', config('app.name')) }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.5.0/dist/tabler-icons.min.css">
    <style>
        body{background:linear-gradient(135deg,#0b1220 0%,#1d2a4d 100%);min-height:100vh;min-height:100dvh}
        .guest-page.page-center{flex:1;min-height:0!important;display:flex;align-items:center;justify-content:center;padding-top:.5rem}
        .guest-page .guest-brand{margin-bottom:1rem!important}
        .guest-footer{flex-shrink:0}

        @media (display-mode: standalone), (display-mode: fullscreen) {
            html,body{height:100%;max-height:100dvh;overflow:hidden}
            .guest-page.page-center{padding-top:max(.25rem,env(safe-area-inset-top))}
            .guest-page .guest-brand{margin-bottom:.75rem!important}
            .guest-page .container-tight{padding-top:.25rem!important}
            .guest-footer{padding-bottom:max(.5rem,env(safe-area-inset-bottom))}
        }

        html.pwa-standalone,html.pwa-standalone body{height:100%;max-height:100dvh;overflow:hidden}
        html.pwa-standalone .guest-page.page-center{padding-top:max(.25rem,env(safe-area-inset-top))}
        html.pwa-standalone .guest-page .guest-brand{margin-bottom:.75rem!important}
        html.pwa-standalone .guest-page .container-tight{padding-top:.25rem!important}
        html.pwa-standalone .guest-footer{padding-bottom:max(.5rem,env(safe-area-inset-bottom))}
    </style>
    <script>
        (function () {
            if (window.matchMedia('(display-mode: standalone)').matches
                || window.matchMedia('(display-mode: fullscreen)').matches
                || window.navigator.standalone === true) {
                document.documentElement.classList.add('pwa-standalone');
            }
        })();
    </script>
    <script>
        (function () {
            var STORAGE_KEY = 'vsla-pwa-installed';
            var START_URL = '/dashboard';
            var deferredInstallPrompt = null;

            function isRunningAsInstalledApp() {
                return window.matchMedia('(display-mode: standalone)').matches
                    || window.matchMedia('(display-mode: fullscreen)').matches
                    || window.navigator.standalone === true;
            }

            function setPwaState(state) {
                var wrap = document.getElementById('pwa-install-wrap');
                if (!wrap) return;

                ['install', 'installing', 'open'].forEach(function (name) {
                    var section = document.getElementById('pwa-state-' + name);
                    if (section) {
                        section.classList.toggle('d-none', name !== state);
                    }
                });

                wrap.classList.toggle('d-none', !state);
            }

            function markInstalled() {
                try {
                    localStorage.setItem(STORAGE_KEY, '1');
                } catch (error) {}

                setPwaState('open');
            }

            function isMarkedInstalled() {
                try {
                    return localStorage.getItem(STORAGE_KEY) === '1';
                } catch (error) {
                    return false;
                }
            }

            async function checkInstalledRelatedApps() {
                if (!navigator.getInstalledRelatedApps) {
                    return false;
                }

                try {
                    var apps = await navigator.getInstalledRelatedApps();
                    return Array.isArray(apps) && apps.length > 0;
                } catch (error) {
                    return false;
                }
            }

            async function refreshPwaUi() {
                if (isRunningAsInstalledApp()) {
                    setPwaState(null);
                    return;
                }

                if (isMarkedInstalled() || await checkInstalledRelatedApps()) {
                    markInstalled();
                    return;
                }

                setPwaState('install');
            }

            window.addEventListener('beforeinstallprompt', function (event) {
                event.preventDefault();
                deferredInstallPrompt = event;

                if (!isRunningAsInstalledApp() && !isMarkedInstalled()) {
                    setPwaState('install');
                }
            });

            window.addEventListener('appinstalled', function () {
                deferredInstallPrompt = null;
                markInstalled();
            });

            document.addEventListener('DOMContentLoaded', function () {
                refreshPwaUi();

                var installButton = document.getElementById('pwa-install-button');
                if (installButton) {
                    installButton.addEventListener('click', async function () {
                        if (!deferredInstallPrompt) {
                            window.alert('To install VSLA Manager, open your browser menu and choose “Install app” or “Add to home screen”.');
                            return;
                        }

                        setPwaState('installing');

                        try {
                            deferredInstallPrompt.prompt();
                            var choice = await deferredInstallPrompt.userChoice;
                            deferredInstallPrompt = null;

                            if (choice.outcome === 'accepted') {
                                markInstalled();
                            } else {
                                setPwaState('install');
                            }
                        } catch (error) {
                            setPwaState('install');
                        }
                    });
                }

                var openButton = document.getElementById('pwa-open-button');
                if (openButton) {
                    openButton.addEventListener('click', function () {
                        window.location.href = START_URL;
                    });
                }
            });
        })();

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function (error) {
                    console.warn('PWA service worker registration failed:', error);
                });
            });
        }
    </script>
</head>
<body class="d-flex flex-column">
    <div class="page page-center guest-page">
        <div class="container container-tight pt-2 pb-3">
            <div class="text-center guest-brand mb-3">
                <a href="{{ route('login') }}" class="navbar-brand navbar-brand-autodark text-white text-decoration-none h2">
                    @php $sysLogo = \App\Models\SystemSetting::publicUrl(\App\Models\SystemSetting::get('app_logo')); $sysName = \App\Models\SystemSetting::get('app_name', config('app.name')); @endphp
                    @if($sysLogo)
                        <img src="{{ $sysLogo }}" alt="{{ $sysName }}" style="height:48px;max-width:180px;object-fit:contain;vertical-align:middle;margin-right:8px;">
                    @else
                        <i class="ti ti-coin"></i>
                    @endif
                    {{ $sysName }}
                </a>
            </div>
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </div>
    </div>
    <div class="guest-footer text-center text-white-50 small pb-2">{{ __('Built by') }} <strong>Success Path Ltd</strong></div>
    @stack('scripts')
</body>
</html>
