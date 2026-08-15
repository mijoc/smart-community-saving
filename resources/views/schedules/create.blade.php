@extends('layouts.app')
@section('title','New schedule')
@section('content')
<x-page_header :title="'New schedule for '.$group->name" pretitle="Schedules"></x-page_header>
<form method="POST" action="{{ route('groups.schedules.store', $group) }}" class="card mt-3">
    <div class="card-body">@include('schedules._form')</div>
    <div class="card-footer text-end">
        <a href="{{ route('groups.schedules.index', $group) }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Create schedule</button>
    </div>
</form>
@endsection
