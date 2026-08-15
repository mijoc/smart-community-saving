@extends('layouts.app')
@section('title','New user')
@section('content')
<x-page_header title="New user" pretitle="Users"></x-page_header>
<form method="POST" action="{{ route('users.store') }}" class="card mt-3">
    <div class="card-body">@include('users._form')</div>
    <div class="card-footer text-end">
        <a href="{{ route('users.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Create user</button>
    </div>
</form>
@endsection
