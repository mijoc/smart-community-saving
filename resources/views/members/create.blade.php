@extends('layouts.app')
@section('title','New member')
@section('content')
<x-page_header title="New member" pretitle="Members"></x-page_header>
<form method="POST" action="{{ route('members.store') }}" enctype="multipart/form-data" class="card mt-3">
    <div class="card-body">@include('members._form')</div>
    <div class="card-footer text-end">
        <a href="{{ route('members.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Create member</button>
    </div>
</form>
@endsection
