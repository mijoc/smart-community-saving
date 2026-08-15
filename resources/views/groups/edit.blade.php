@extends('layouts.app')
@section('title','Edit group')
@section('content')
<x-page_header :title="'Edit '.$group->name" pretitle="Groups"></x-page_header>
<form method="POST" action="{{ route('groups.update', $group) }}" class="card mt-3">@method('PUT')
    <div class="card-body">@include('groups._form')</div>
    <div class="card-footer text-end">
        <a href="{{ route('groups.show', $group) }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
@endsection
