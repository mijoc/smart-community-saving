@extends('layouts.guest')
@section('title','Choose your group')
@section('content')

<div class="container container-tight py-4">
    <div class="text-center mb-4">
        <i class="ti ti-coin fs-1 text-primary"></i>
        <h2 class="mt-2">Pick the group you want to work in</h2>
        <p class="text-muted">You belong to more than one group. Pick one to continue. You can switch any time from the topbar.</p>
    </div>

    <div class="card card-md">
        <div class="card-body">
            @if($groups->isEmpty())
                <div class="empty">
                    <p class="empty-title">No groups yet</p>
                    <p class="empty-subtitle text-muted">You are not assigned to any group. Please contact a super admin.</p>
                </div>
            @else
                <div class="list-group list-group-flush">
                @foreach($groups as $g)
                    <form method="POST" action="{{ route('groups.switch') }}">@csrf
                        <input type="hidden" name="group_id" value="{{ $g->id }}">
                        <button class="list-group-item list-group-item-action d-flex align-items-center {{ $current==$g->id ? 'active' : '' }}">
                            <div class="me-3"><span class="avatar bg-primary-lt"><i class="ti ti-users-group"></i></span></div>
                            <div class="text-start flex-grow-1">
                                <div class="fw-bold">{{ $g->name }}</div>
                                <div class="text-muted small">{{ $g->code }} · {{ $g->village ?? '—' }}, {{ $g->district ?? '—' }} · {{ $g->currency }}</div>
                            </div>
                            <i class="ti ti-chevron-right"></i>
                        </button>
                    </form>
                @endforeach
                </div>

                @if(auth()->user()->isSuperAdmin())
                    <hr>
                    <form method="POST" action="{{ route('groups.switch') }}" class="text-center">@csrf
                        <button class="btn btn-outline-secondary"><i class="ti ti-world me-1"></i> Browse all groups (super-admin global view)</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <div class="text-center text-muted mt-3">
        <form method="POST" action="{{ route('logout') }}" class="d-inline">@csrf
            <button class="btn btn-link text-muted">Sign out</button>
        </form>
    </div>
</div>

@endsection
