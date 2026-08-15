@extends('layouts.app')
@section('title', 'My profile')
@section('content')

<x-page_header title="My profile" pretitle="Account"></x-page_header>

@if($errors->any())
<div class="alert alert-danger mt-3">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="row row-cards mt-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <label for="avatar-input" class="avatar-upload mb-3" title="Click to change profile picture">
                    <span class="avatar avatar-xl" id="avatar-preview" style="background-image:url('{{ $user->avatar_url }}')"></span>
                    <span class="avatar-upload-overlay">
                        <i class="ti ti-camera"></i>
                        <span>Change photo</span>
                    </span>
                </label>
                <h3 class="mb-0">{{ $user->name }}</h3>
                <div class="text-muted">{{ $user->email }}</div>
                <div class="mt-3">
                    @foreach($user->roles as $r)
                        <span class="badge bg-blue-lt me-1">{{ str_replace('_',' ',$r->name) }}</span>
                    @endforeach
                </div>
                @if($user->member)
                    <div class="text-muted small mt-3">Linked member: <strong>{{ $user->member->full_name }}</strong> ({{ $user->member->member_no }})</div>
                @endif
                <p class="text-muted small mt-3 mb-0">JPG, PNG or GIF · max 2 MB</p>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        {{-- ─── Profile details ─── --}}
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card" id="profile-form">@csrf @method('PUT')
            <div class="card-header"><h3 class="card-title">Profile details</h3></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Full name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Username</label>
                    @if($user->canChangeUsername())
                        <input type="text" name="username" value="{{ old('username', $user->username) }}" class="form-control" required autocomplete="username">
                    @else
                        <input type="text" value="{{ $user->username }}" class="form-control" readonly disabled>
                        <div class="form-text">Members cannot change their username. Contact an administrator if you need it updated.</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Profile picture</label>
                    <input type="file" name="avatar" id="avatar-input" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">Choose a new photo, then click <strong>Save changes</strong>. You can also click your photo on the left.</div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save changes</button>
            </div>
        </form>

        {{-- ─── Password change ─── --}}
        <form method="POST" action="{{ route('profile.password') }}" class="card mt-3">@csrf @method('PUT')
            <div class="card-header"><h3 class="card-title">Change password</h3></div>
            <div class="card-body row g-3">
                <div class="col-md-12">
                    <label class="form-label required">Current password</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">New password</label>
                    <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Confirm new password</label>
                    <input type="password" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-warning"><i class="ti ti-key me-1"></i>Update password</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .avatar-upload {
        position: relative;
        display: inline-block;
        cursor: pointer;
        border-radius: 50%;
    }
    .avatar-upload .avatar-upload-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: .15rem;
        border-radius: 50%;
        background: rgba(15, 23, 42, .55);
        color: #fff;
        font-size: .75rem;
        opacity: 0;
        transition: opacity .2s ease;
    }
    .avatar-upload:hover .avatar-upload-overlay,
    .avatar-upload:focus-within .avatar-upload-overlay {
        opacity: 1;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('avatar-input');
    const preview = document.getElementById('avatar-preview');
    if (!input || !preview) return;

    input.addEventListener('change', function () {
        const file = input.files?.[0];
        if (!file) return;
        preview.style.backgroundImage = "url('" + URL.createObjectURL(file) + "')";
    });
});
</script>
@endpush
@endsection
