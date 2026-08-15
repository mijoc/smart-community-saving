@extends('layouts.app')
@section('title','Edit member')
@section('content')
<x-page_header :title="'Edit '.$member->full_name" pretitle="Members"></x-page_header>
<form method="POST" action="{{ route('members.update', $member) }}" enctype="multipart/form-data" class="card mt-3">
    @method('PUT')
    <div class="card-body">@include('members._form')</div>
    <div class="card-footer text-end">
        <a href="{{ route('members.show', $member) }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Save changes</button>
    </div>
</form>
@endsection
