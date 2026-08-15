{{-- Language switcher dropdown with flag icons.
     Works for both authenticated users (saves to users.locale) and
     guests on the login page (saves to session). --}}
@php
    $current = app()->getLocale();
    $languages = [
        'en' => ['name' => __('English'),     'flag' => 'gb'],
        'rw' => ['name' => __('Kinyarwanda'), 'flag' => 'rw'],
        'fr' => ['name' => __('French'),      'flag' => 'fr'],
    ];
    $currentLang = $languages[$current] ?? $languages['en'];
@endphp

<div class="nav-item dropdown {{ $wrapperClass ?? '' }}">
    <a href="#" class="nav-link d-flex align-items-center px-2" data-bs-toggle="dropdown"
       aria-expanded="false" title="{{ __('Language') }}">
        <img src="https://flagcdn.com/24x18/{{ $currentLang['flag'] }}.png"
             srcset="https://flagcdn.com/48x36/{{ $currentLang['flag'] }}.png 2x"
             width="24" height="18" alt="{{ $currentLang['name'] }}"
             class="rounded">
        <span class="d-none d-md-inline ms-2 small">{{ $currentLang['name'] }}</span>
        <i class="ti ti-chevron-down ms-1"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
        <div class="dropdown-header text-muted">{{ __('Language') }}</div>
        @foreach($languages as $code => $lang)
            <form method="POST" action="{{ route('locale.switch') }}" class="m-0">@csrf
                <input type="hidden" name="locale"      value="{{ $code }}">
                <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                <button type="submit"
                        class="dropdown-item d-flex align-items-center {{ $current === $code ? 'active' : '' }}">
                    <img src="https://flagcdn.com/24x18/{{ $lang['flag'] }}.png"
                         srcset="https://flagcdn.com/48x36/{{ $lang['flag'] }}.png 2x"
                         width="24" height="18" alt="{{ $lang['name'] }}"
                         class="rounded me-2">
                    <span class="flex-grow-1">{{ $lang['name'] }}</span>
                    @if($current === $code)
                        <i class="ti ti-check text-success ms-2"></i>
                    @endif
                </button>
            </form>
        @endforeach
    </div>
</div>
