@extends('layouts.guest')
@section('title', 'Create account')
@section('content')
<form class="card card-md" method="POST" action="{{ route('register') }}" autocomplete="off">@csrf
    <div class="card-body">
        <h2 class="h2 text-center mb-4">Create your account</h2>
        <div class="mb-3"><label class="form-label">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Username</label>
            <input type="text" name="username" value="{{ old('username') }}" class="form-control" required autocomplete="username">
            <small class="text-muted">Use letters, numbers, dashes or underscores.</small></div>
        <div class="mb-3"><label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Confirm password</label>
            <input type="password" name="password_confirmation" class="form-control" required></div>
        <div class="form-footer"><button type="submit" class="btn btn-primary w-100">Create account</button></div>
    </div>
    <div class="card-footer text-center text-muted small">
        Already have one? <a href="{{ route('login') }}">Sign in</a>
    </div>
</form>
@endsection
