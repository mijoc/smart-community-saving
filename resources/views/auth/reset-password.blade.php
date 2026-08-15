@extends('layouts.guest')
@section('title', 'Set new password')
@section('content')
<form class="card card-md" method="POST" action="{{ route('password.update') }}">@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="card-body">
        <h2 class="h2 text-center mb-3">Set a new password</h2>
        <div class="mb-3"><label class="form-label">Email</label>
            <input type="email" name="email" value="{{ request()->email }}" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">New password</label>
            <input type="password" name="password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Confirm password</label>
            <input type="password" name="password_confirmation" class="form-control" required></div>
        <button class="btn btn-primary w-100" type="submit">Update password</button>
    </div>
</form>
@endsection
