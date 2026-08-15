<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#091a35">
    <title>{{ __('Coming soon') }} · {{ $appName }}</title>
    <link rel="icon" href="{{ $appLogo ?: '/icons/icon.svg' }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.5.0/dist/tabler-icons.min.css">
    <style>
        :root {
            --navy: #091a35;
            --blue: #2475e8;
            --cyan: #42d8df;
            --ink: #16243b;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            color: #fff;
            background:
                radial-gradient(circle at 15% 15%, rgba(66, 216, 223, .16), transparent 30%),
                radial-gradient(circle at 85% 75%, rgba(36, 117, 232, .25), transparent 35%),
                var(--navy);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .construction-shell {
            min-height: 100svh;
            height: 100svh;
            display: flex;
            align-items: center;
            padding: clamp(1rem, 4vh, 2rem) 1.25rem;
            position: relative;
            overflow: hidden;
        }
        .construction-shell::before,
        .construction-shell::after {
            content: "";
            position: absolute;
            width: 22rem;
            height: 22rem;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 50%;
            pointer-events: none;
        }
        .construction-shell::before { top: -12rem; right: -7rem; box-shadow: 0 0 0 2rem rgba(255,255,255,.02), 0 0 0 4rem rgba(255,255,255,.015); }
        .construction-shell::after { bottom: -15rem; left: -9rem; }
        .construction-card {
            width: min(100%, 920px);
            margin: auto;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: .9fr 1.1fr;
            gap: clamp(2rem, 6vw, 6rem);
            align-items: center;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: .75rem;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: -.01em;
        }
        .brand-mark {
            width: 2.75rem;
            height: 2.75rem;
            display: grid;
            place-items: center;
            border-radius: .9rem;
            background: linear-gradient(135deg, var(--cyan), var(--blue));
            box-shadow: 0 10px 28px rgba(36,117,232,.35);
        }
        .brand-mark img { max-width: 2rem; max-height: 2rem; object-fit: contain; }
        .brand-mark i { font-size: 1.5rem; }
        .account-actions {
            position: absolute;
            top: 1.25rem;
            left: max(1.25rem, calc((100% - 920px) / 2));
            right: max(1.25rem, calc((100% - 920px) / 2));
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
        }
        .welcome {
            color: rgba(255,255,255,.72);
            font-size: clamp(1rem, 1.8vw, 1.35rem);
            letter-spacing: -.02em;
        }
        .welcome strong { color: #fff; }
        .logout-button {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .55rem .8rem;
            color: #fff;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: .6rem;
            background: rgba(7, 20, 43, .58);
            font: inherit;
            cursor: pointer;
            transition: background .2s ease, border-color .2s ease;
        }
        .logout-button:hover,
        .logout-button:focus-visible {
            background: rgba(36,117,232,.72);
            border-color: rgba(255,255,255,.42);
        }
        .eyebrow {
            color: var(--cyan);
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        h1 {
            max-width: 15ch;
            margin: clamp(.45rem, 1.5vh, .8rem) 0 clamp(.7rem, 2vh, 1.25rem);
            font-size: clamp(2.7rem, 8vh, 5.6rem);
            line-height: .98;
            letter-spacing: -.065em;
        }
        .message {
            max-width: 34rem;
            margin: 0;
            color: rgba(255,255,255,.72);
            font-size: clamp(1rem, 1.7vw, 1.2rem);
            line-height: 1.7;
            white-space: pre-line;
        }
        .contact-line {
            color: rgba(255,255,255,.58);
            font-size: .9rem;
        }
        .contact-line a {
            color: var(--cyan);
            font-weight: 700;
            text-decoration: none;
        }
        .contact-line a:hover,
        .contact-line a:focus {
            color: #fff;
            text-decoration: underline;
        }
        .illustration {
            min-height: min(25rem, 58vh);
            display: grid;
            place-items: center;
            position: relative;
        }
        .orb {
            width: min(72vw, 34vh, 24rem);
            aspect-ratio: 1;
            border-radius: 50%;
            background: linear-gradient(145deg, rgba(66,216,223,.18), rgba(36,117,232,.08));
            border: 1px solid rgba(255,255,255,.15);
            box-shadow: inset 0 0 60px rgba(66,216,223,.08), 0 30px 80px rgba(0,0,0,.25);
            display: grid;
            place-items: center;
        }
        .orb-inner {
            width: 63%;
            aspect-ratio: 1;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, var(--blue), #1645a1);
            box-shadow: 0 20px 50px rgba(36,117,232,.4);
        }
        .orb-inner i { font-size: clamp(4rem, 10vw, 6.5rem); color: #fff; transform: rotate(-12deg); }
        .status-chip {
            position: absolute;
            right: 2%;
            bottom: 12%;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem .9rem;
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 999px;
            color: rgba(255,255,255,.88);
            background: rgba(7, 20, 43, .7);
            backdrop-filter: blur(12px);
            font-size: .82rem;
            box-shadow: 0 12px 28px rgba(0,0,0,.2);
        }
        .status-dot { width: .5rem; height: .5rem; border-radius: 50%; background: var(--cyan); box-shadow: 0 0 0 .25rem rgba(66,216,223,.14); }
        .credit {
            position: absolute;
            left: 1.25rem;
            bottom: 1.25rem;
            color: rgba(255,255,255,.42);
            font-size: .75rem;
            letter-spacing: .04em;
        }
        @media (min-width: 768px) and (max-height: 700px) {
            .construction-card { gap: clamp(1.5rem, 4vw, 4rem); }
            .illustration { min-height: 15rem; }
            .orb { width: min(30vh, 15rem); }
            .orb-inner i { font-size: 4rem; }
            h1 { font-size: clamp(2.35rem, 7vh, 4rem); }
            .message { font-size: 1rem; line-height: 1.5; }
        }
        @media (max-width: 767.98px) {
            .construction-shell {
                min-height: 100svh;
                height: 100svh;
                padding: 1rem 1rem 2.25rem;
                overflow: hidden;
            }
            .account-actions {
                top: .75rem;
                right: .75rem;
                left: .75rem;
                justify-content: space-between;
            }
            .welcome {
                display: block;
                max-width: calc(100vw - 9rem);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: clamp(1rem, 4.5vw, 1.3rem);
            }
            .logout-button { padding: .45rem .6rem; font-size: .78rem; flex-shrink: 0; }
            .construction-card {
                height: 100%;
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
                gap: 0;
                align-content: start;
            }
            .brand { font-size: .95rem; }
            .brand-mark { width: 2.35rem; height: 2.35rem; border-radius: .75rem; }
            .brand-mark img { max-width: 1.75rem; max-height: 1.75rem; }
            .illustration {
                min-height: 9.5rem;
                order: -1;
            }
            .orb { width: 9.5rem; }
            .orb-inner i { font-size: 3.8rem; }
            .status-chip {
                right: 7%;
                bottom: 2%;
                padding: .45rem .7rem;
                font-size: .7rem;
            }
            .eyebrow.mt-5 { margin-top: .65rem !important; }
            h1 {
                max-width: 12ch;
                margin: .4rem 0 .7rem;
                font-size: clamp(2.2rem, 11vw, 3.25rem);
                line-height: .92;
            }
            .message {
                font-size: .95rem;
                line-height: 1.45;
            }
            .contact-line.mt-4 {
                margin-top: .85rem !important;
                font-size: .8rem;
            }
            .credit {
                left: 1rem;
                bottom: .65rem;
                font-size: .68rem;
            }
        }
        @media (max-width: 767.98px) and (max-height: 700px) {
            .construction-shell { padding-top: .7rem; padding-bottom: 1.9rem; }
            .illustration { min-height: 7.2rem; }
            .orb { width: 7.2rem; }
            .orb-inner i { font-size: 2.8rem; }
            .eyebrow.mt-5 { margin-top: .35rem !important; }
            h1 {
                margin-top: .25rem;
                margin-bottom: .45rem;
                font-size: clamp(1.9rem, 10vw, 2.65rem);
            }
            .message { font-size: .88rem; line-height: 1.35; }
            .contact-line.mt-4 { margin-top: .55rem !important; }
        }
    </style>
</head>
<body>
    <main class="construction-shell">
        @if($user ?? null)
            <div class="account-actions">
                <span class="welcome">{{ __('Hi') }}, <strong>{{ $user->name }}</strong></span>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="logout-button">
                        <i class="ti ti-logout-2" aria-hidden="true"></i>
                        <span>{{ __('Logout') }}</span>
                    </button>
                </form>
            </div>
        @endif
        <div class="construction-card">
            <section>
                <a class="brand" href="{{ route('under-construction') }}">
                    <span class="brand-mark">
                        @if($appLogo)
                            <img src="{{ $appLogo }}" alt="">
                        @else
                            <i class="ti ti-building-community"></i>
                        @endif
                    </span>
                    <span>{{ $appName }}</span>
                </a>
                <p class="eyebrow mt-5 mb-0">{{ __('A better experience is on its way') }}</p>
                <h1>{{ __('We are building something great.') }}</h1>
                <p class="message">{{ $message }}</p>
                <p class="contact-line mt-4 mb-0">
                    {{ __('Other Issues?') }}
                    <a href="tel:0783475458">Contact 0783475458</a>
                </p>
            </section>
            <section class="illustration" aria-label="{{ __('Application under construction') }}">
                <div class="orb">
                    <div class="orb-inner"><i class="ti ti-tool"></i></div>
                </div>
                <div class="status-chip"><span class="status-dot"></span>{{ __('Under construction') }}</div>
            </section>
        </div>
        <div class="credit">{{ __('Project built by') }} <strong>Success Path Ltd</strong></div>
    </main>
</body>
</html>