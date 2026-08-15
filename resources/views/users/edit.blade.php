@extends('layouts.app')
@section('title','Edit user')
@section('content')
<x-page_header :title="'Edit '.$user->name" pretitle="Users"></x-page_header>
<form method="POST" action="{{ route('users.update', $user) }}" class="card mt-3">@method('PUT')
    <div class="card-body">@include('users._form')</div>
    <div class="card-footer text-end">
        <a href="{{ route('users.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
@endsection
