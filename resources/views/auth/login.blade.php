@extends('layouts.guest')
@section('title', __('Sign in'))
@section('content')
<form class="card card-md" method="POST" action="{{ route('login') }}" autocomplete="off">@csrf
    <div class="card-body">
        <h2 class="h2 text-center mb-4">{{ __('Sign in to your account') }}</h2>
        <div class="mb-3">
            <label class="form-label">{{ __('Username') }}</label>
            <input type="text" name="username" value="{{ old('username') }}" class="form-control" placeholder="your username" required autofocus autocomplete="username">
        </div>
        <div class="mb-2">
            <label class="form-label">{{ __('Password') }}</label>
            <div class="input-group input-group-flat">
                <input id="login-password" type="password" name="password" class="form-control" placeholder="{{ __('Your password') }}" required autocomplete="current-password">
                <button id="toggle-login-password" type="button" class="btn btn-icon" aria-label="{{ __('Show password') }}" title="{{ __('Show password') }}">
                    <i class="ti ti-eye" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-check">
                <input type="checkbox" name="remember" class="form-check-input">
                <span class="form-check-label">{{ __('Remember me') }}</span>
            </label>
        </div>
        <div class="form-footer">
            <button type="submit" class="btn btn-primary w-100">{{ __('Sign in') }}</button>
        </div>
        <div id="pwa-install-wrap" class="d-none mt-3">
            <div id="pwa-state-install">
                <button id="pwa-install-button" type="button" class="btn btn-outline-primary w-100">
                    <i class="ti ti-download me-1" aria-hidden="true"></i>
                    {{ __('Install VSLA Manager') }}
                </button>
                <div class="form-hint text-center mt-2">
                    {{ __('Install the app on this device for faster access.') }}
                </div>
            </div>
            <div id="pwa-state-installing" class="d-none">
                <button type="button" class="btn btn-outline-primary w-100" disabled aria-busy="true">
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    {{ __('Installing...') }}
                </button>
                <div class="progress progress-sm mt-2" role="progressbar" aria-label="{{ __('Installation progress') }}">
                    <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
                </div>
                <div class="form-hint text-center mt-2">
                    {{ __('Please wait while the app is being installed on your device.') }}
                </div>
            </div>
            <div id="pwa-state-open" class="d-none">
                <button id="pwa-open-button" type="button" class="btn btn-success w-100">
                    <i class="ti ti-app-window me-1" aria-hidden="true"></i>
                    {{ __('Open app') }}
                </button>
                <div class="form-hint text-center mt-2">
                    {{ __('VSLA Manager is installed on this device.') }}
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    (function () {
        var password = document.getElementById('login-password');
        var toggle = document.getElementById('toggle-login-password');
        if (!password || !toggle) return;

        toggle.addEventListener('click', function () {
            var shouldShow = password.type === 'password';
            password.type = shouldShow ? 'text' : 'password';
            toggle.setAttribute('aria-label', shouldShow ? '{{ __('Hide password') }}' : '{{ __('Show password') }}');
            toggle.setAttribute('title', shouldShow ? '{{ __('Hide password') }}' : '{{ __('Show password') }}');
            toggle.querySelector('i').className = shouldShow ? 'ti ti-eye-off' : 'ti ti-eye';
        });
    })();
</script>
@endpush
