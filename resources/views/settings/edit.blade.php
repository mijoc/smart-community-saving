@extends('layouts.app')
@section('title', __('Settings'))
@section('content')

<x-page_header :title="__('Settings')" :pretitle="__('Account')"></x-page_header>

@if(session('status'))
<div class="alert alert-success alert-dismissible mt-3">
    <i class="ti ti-circle-check me-2"></i>{{ session('status') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger mt-3">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="row row-cards mt-3">

    {{-- Left sidebar: avatar + info --}}
    <div class="col-md-3">
        <div class="card text-center p-3">
            <span class="avatar avatar-xl mx-auto mb-3"
                  style="background-image:url('{{ $user->avatar_url }}')"></span>
            <div class="fw-bold">{{ $user->name }}</div>
            <div class="text-muted small">{{ $user->email }}</div>
            <div class="mt-2">
                @foreach($user->roles as $r)
                    <span class="badge bg-blue-lt">{{ str_replace('_',' ',$r->name) }}</span>
                @endforeach
            </div>
            @if($user->member)
            <div class="text-muted small mt-2">
                {{ $user->member->member_no }}
            </div>
            @endif
            <div class="mt-3 d-grid gap-1">
                <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-user me-1"></i>{{ __('Edit profile') }}
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-9">

        {{-- ── 1. Language & Region ─────────────────────────────────────── --}}
        <form method="POST" action="{{ route('settings.update') }}" id="settings-form">
        @csrf @method('PUT')

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-language me-2 text-blue"></i>{{ __('Language & Region') }}
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('Interface language') }}</label>
                        <select name="locale" class="form-select">
                            <option value="en" @selected(($user->locale ?? 'en') === 'en')>🇬🇧 English</option>
                            <option value="rw" @selected(($user->locale ?? 'en') === 'rw')>🇷🇼 Kinyarwanda</option>
                            <option value="fr" @selected(($user->locale ?? 'en') === 'fr')>🇫🇷 Français</option>
                        </select>
                        <div class="form-text">{{ __('Changes the language of all menus, labels and messages.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 2. Notifications ──────────────────────────────────────────── --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-bell me-2 text-yellow"></i>{{ __('Notification Preferences') }}
                </h3>
                <div class="card-options">
                    <span class="text-muted small">{{ __('Controls what appears in your bell notification feed.') }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-12">
                        <label class="row">
                            <span class="col">
                                <span class="d-block fw-semibold">{{ __('Group activity') }}</span>
                                <span class="d-block text-muted small">{{ __('Member joins, payments received, loans approved, etc.') }}</span>
                            </span>
                            <span class="col-auto">
                                <label class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notify_activity"
                                           value="1" @checked($user->pref('notify_activity', true))>
                                </label>
                            </span>
                        </label>
                    </div>

                    <div class="col-12">
                        <label class="row">
                            <span class="col">
                                <span class="d-block fw-semibold">{{ __('Contribution reminders') }}</span>
                                <span class="d-block text-muted small">{{ __('Alerts when a contribution is due or overdue.') }}</span>
                            </span>
                            <span class="col-auto">
                                <label class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notify_contributions"
                                           value="1" @checked($user->pref('notify_contributions', true))>
                                </label>
                            </span>
                        </label>
                    </div>

                    <div class="col-12">
                        <label class="row">
                            <span class="col">
                                <span class="d-block fw-semibold">{{ __('Loan updates') }}</span>
                                <span class="d-block text-muted small">{{ __('When your loan request is approved, rejected or disbursed.') }}</span>
                            </span>
                            <span class="col-auto">
                                <label class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notify_loans"
                                           value="1" @checked($user->pref('notify_loans', true))>
                                </label>
                            </span>
                        </label>
                    </div>

                    <div class="col-12">
                        <label class="row">
                            <span class="col">
                                <span class="d-block fw-semibold">{{ __('Group announcements') }}</span>
                                <span class="d-block text-muted small">{{ __('Important notices posted by the group admin.') }}</span>
                            </span>
                            <span class="col-auto">
                                <label class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notify_announcements"
                                           value="1" @checked($user->pref('notify_announcements', true))>
                                </label>
                            </span>
                        </label>
                    </div>

                </div>
            </div>
        </div>

        {{-- ── 3. Privacy ────────────────────────────────────────────────── --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-shield me-2 text-green"></i>{{ __('Privacy') }}
                </h3>
            </div>
            <div class="card-body">
                <label class="row">
                    <span class="col">
                        <span class="d-block fw-semibold">{{ __('Show my phone number in the member directory') }}</span>
                        <span class="d-block text-muted small">{{ __('When off, only admins can see your phone number.') }}</span>
                    </span>
                    <span class="col-auto">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="show_phone_in_directory"
                                   value="1" @checked($user->pref('show_phone_in_directory', false))>
                        </label>
                    </span>
                </label>
            </div>
        </div>

        {{-- Save button --}}
        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary px-4">
                <i class="ti ti-device-floppy me-1"></i>{{ __('Save settings') }}
            </button>
        </div>

        </form>{{-- end settings-form --}}

        {{-- ── 4. Change Password ────────────────────────────────────────── --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-key me-2 text-orange"></i>{{ __('Change Password') }}
                </h3>
            </div>
            <form method="POST" action="{{ route('settings.password') }}">@csrf @method('PUT')
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label required">{{ __('Current password') }}</label>
                    <input type="password" name="current_password" class="form-control"
                           required autocomplete="current-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">{{ __('New password') }}</label>
                    <input type="password" name="password" class="form-control"
                           required minlength="8" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">{{ __('Confirm new password') }}</label>
                    <input type="password" name="password_confirmation" class="form-control"
                           required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-warning">
                    <i class="ti ti-lock me-1"></i>{{ __('Update password') }}
                </button>
            </div>
            </form>
        </div>

        {{-- ── 5. Account info (read-only) ──────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-info-circle me-2 text-muted"></i>{{ __('Account Information') }}
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted">{{ __('Account created') }}</label>
                        <div class="fw-semibold">{{ $user->created_at->format('d M Y') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted">{{ __('Last login') }}</label>
                        <div class="fw-semibold">{{ $user->updated_at->diffForHumans() }}</div>
                    </div>
                    @if($user->member)
                    <div class="col-md-6">
                        <label class="form-label text-muted">{{ __('Linked member') }}</label>
                        <div class="fw-semibold">{{ $user->member->full_name }} · {{ $user->member->member_no }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted">{{ __('Member since') }}</label>
                        <div class="fw-semibold">{{ $user->member->joined_on ? \Carbon\Carbon::parse($user->member->joined_on)->format('d M Y') : '—' }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

    </div>{{-- col-md-9 --}}
</div>
@endsection
