@extends('layouts.guest')
@section('title', 'Reset password')
@section('content')
<form class="card card-md" method="POST" action="{{ route('password.email') }}">@csrf
    <div class="card-body">
        <h2 class="h2 text-center mb-3">Forgot password</h2>
        <p class="text-muted small mb-3">Enter your email and we'll send you a reset link.</p>
        <div class="mb-3"><label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required></div>
        <button class="btn btn-primary w-100" type="submit">Send reset link</button>
    </div>
    <div class="card-footer text-center small"><a href="{{ route('login') }}">Back to sign in</a></div>
</form>
@endsection
