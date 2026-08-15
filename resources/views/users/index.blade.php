@extends('layouts.app')
@section('title','Users & roles')
@section('content')
<x-page_header title="Users & roles" pretitle="Administration">
    <x-slot name="actions">
        <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>New user</a>
    </x-slot>
</x-page_header>

<div class="card mt-3">
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Roles</th><th>Linked member</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach($users as $u)
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="avatar avatar-sm me-2" style="background-image:url('{{ $u->avatar_url }}')"></span>
                        <strong>{{ $u->name }}</strong>
                    </div>
                </td>
                <td>{{ $u->username }}</td>
                <td>{{ $u->email }}</td>
                <td>
                    @foreach($u->roles as $r)<span class="badge bg-blue-lt me-1">{{ str_replace('_',' ',$r->name) }}</span>@endforeach
                </td>
                <td>{{ $u->member?->full_name ?? '—' }}</td>
                <td><span class="badge bg-{{ $u->is_active ? 'green' : 'secondary' }}-lt">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
                <td class="text-end">
                    <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-edit"></i></a>
                    <form method="POST" action="{{ route('users.destroy', $u) }}" class="d-inline" onsubmit="return confirm('Remove this user?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $users->links() }}</div>
</div>
@endsection
