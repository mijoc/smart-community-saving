@extends('layouts.app')
@section('title','Passbooks')
@section('content')
<x-page_header title="Member passbooks" pretitle="Statements"></x-page_header>

<div class="card mt-3">
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead><tr><th>#</th><th>Member</th><th>Phone</th><th></th></tr></thead>
            <tbody>
            @foreach($members as $m)
            <tr>
                <td class="text-muted">{{ $m->member_no }}</td>
                <td><strong>{{ $m->full_name }}</strong></td>
                <td>{{ $m->phone }}</td>
                <td class="text-end">
                    <a href="{{ route('passbooks.show', $m) }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-book me-1"></i>Open passbook
                    </a>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $members->links() }}</div>
</div>
@endsection
