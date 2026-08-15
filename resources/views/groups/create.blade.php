@extends('layouts.app')
@section('title','New group')
@section('content')
<x-page_header title="New group" pretitle="Groups"></x-page_header>
<form method="POST" action="{{ route('groups.store') }}" class="card mt-3">
    <div class="card-body">@include('groups._form')</div>
    <div class="card-footer text-end">
        <a href="{{ route('groups.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Create group</button>
    </div>
</form>
@endsection
