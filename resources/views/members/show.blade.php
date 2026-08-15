@extends('layouts.app')
@section('title', $member->full_name)
@section('content')

@php
    $linkedUser = \App\Models\User::where('member_id', $member->id)->first();
    $isAdminish = auth()->user()->hasAnyRole(['super_admin', 'group_admin']);
@endphp

<x-page_header :title="$member->full_name" :pretitle="'Member · '.$member->member_no">
    <x-slot name="actions">
        <a href="{{ route('passbooks.show', $member) }}" class="btn"><i class="ti ti-book me-1"></i>Passbook</a>
        <a href="{{ route('treasury.member', $member) }}" class="btn"><i class="ti ti-building-bank me-1"></i>Equity &amp; debt</a>
        @if($isAdminish)
        <a href="{{ route('members.card', $member) }}" class="btn" target="_blank" rel="noopener"><i class="ti ti-id me-1"></i>{{ __('Print ID card') }}</a>
        @endif
        @can('update', $member)
        <a href="{{ route('members.edit', $member) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>Edit</a>
        @endcan
    </x-slot>
</x-page_header>

@if(session('status'))
<div class="alert alert-success mt-3">{{ session('status') }}</div>
@endif

<div class="row row-cards mt-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <span class="avatar avatar-xl mb-3" style="background-image:url('{{ $member->photo_url }}')"></span>
                <h3 class="mb-0">{{ $member->full_name }}</h3>
                <div class="text-muted">{{ $member->member_no }}</div>
                <div class="mt-3"><span class="badge bg-green-lt">{{ ucfirst($member->status) }}</span></div>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><strong>Phone:</strong> {{ $member->phone ?? '—' }}</li>
                @if($isAdminish)
                <li class="list-group-item"><strong>Email:</strong> {{ $member->email ?? '—' }}</li>
                <li class="list-group-item"><strong>National ID:</strong> {{ $member->national_id ?? '—' }}</li>
                <li class="list-group-item"><strong>Village:</strong> {{ $member->village ?? '—' }}</li>
                <li class="list-group-item"><strong>District:</strong> {{ $member->district ?? '—' }}</li>
                @endif
                <li class="list-group-item"><strong>Joined:</strong> {{ $member->joined_on?->format('Y-m-d') ?? '—' }}</li>
                @if($isAdminish)
                <li class="list-group-item"><strong>Next of kin:</strong> {{ $member->next_of_kin_name ?? '—' }} {{ $member->next_of_kin_phone ? '('.$member->next_of_kin_phone.')' : '' }}</li>
                @endif
            </ul>
        </div>

        @can('update', $member)
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-key me-1"></i>Login & access</h3></div>
            <div class="card-body">
                @if($linkedUser)
                    <div class="mb-2">
                        <div class="text-muted small">Login username</div>
                        <div class="fw-semibold">{{ $linkedUser->username }}</div>
                        <div class="text-muted small mt-1">Recovery email: {{ $linkedUser->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('members.password.reset', $member) }}" class="mt-3">@csrf
                        <label class="form-label small">New password (leave blank to auto-generate)</label>
                        <div class="input-group">
                            <input type="text" name="new_password" class="form-control form-control-sm" placeholder="auto" minlength="8">
                            <button class="btn btn-warning btn-sm" onclick="return confirm('Reset this member\'s password?')">
                                <i class="ti ti-refresh me-1"></i>Reset password
                            </button>
                        </div>
                        <small class="text-muted">The new password will be shown once on this page.</small>
                    </form>
                @else
                    <div class="text-muted small mb-2">This member cannot sign in yet.</div>
                    <form method="POST" action="{{ route('members.login.create', $member) }}">@csrf
                        <div class="mb-2">
                            <label class="form-label small">Login username</label>
                            <input type="text" name="login_username" class="form-control form-control-sm"
                                   placeholder="member_username" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Recovery email</label>
                            <input type="email" name="login_email" class="form-control form-control-sm"
                                   value="{{ $member->email }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Initial password (optional)</label>
                            <input type="text" name="login_password" class="form-control form-control-sm"
                                   placeholder="leave blank to auto-generate" minlength="8">
                        </div>
                        <button class="btn btn-primary btn-sm w-100">
                            <i class="ti ti-user-plus me-1"></i>Create login account
                        </button>
                    </form>
                @endif
            </div>
        </div>
        @endcan
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    @if($activeGroup ?? null)
                        Membership in this group
                    @else
                        Group memberships
                    @endif
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr>
                        @unless($activeGroup ?? null)<th>Group</th>@endunless
                        <th>Position</th><th>Joined</th><th>Shares</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                        @forelse($member->groups as $g)
                        <tr>
                            @unless($activeGroup ?? null)
                                <td><a href="{{ route('groups.show',$g) }}">{{ $g->name }}</a></td>
                            @endunless
                            <td>{{ ucfirst($g->pivot->position) }}</td>
                            <td>{{ $g->pivot->joined_at }}</td>
                            <td>{{ $g->pivot->share_count }}</td>
                            <td>
                                <span class="badge bg-{{ $g->pivot->is_active ? 'green' : 'secondary' }}-lt">
                                    {{ $g->pivot->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ ($activeGroup ?? null) ? 4 : 5 }}" class="text-center text-muted">
                            @if($activeGroup ?? null)Not a member of the current group.@else Not yet in any group.@endif
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Recent contributions</h3></div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr>
                        @unless($activeGroup ?? null)<th>Group</th>@endunless
                        <th>Type</th><th>Period</th><th>Due</th><th>Status</th>
                        <th class="text-end">Expected</th><th class="text-end">Paid</th>
                    </tr></thead>
                    <tbody>
                        @forelse($member->contributions->sortByDesc('due_on')->take(15) as $c)
                        <tr>
                            @unless($activeGroup ?? null)<td>{{ $c->group?->name }}</td>@endunless
                            <td>{{ str_replace('_',' ',$c->type) }}</td>
                            <td>{{ $c->period_start->format('Y-m-d') }}</td>
                            <td>{{ $c->due_on->format('Y-m-d') }}</td>
                            <td>@include('contributions._status', ['status' => $c->status])</td>
                            <td class="text-end">{{ number_format($c->expected_amount, 0) }}</td>
                            <td class="text-end">{{ number_format($c->paid_amount, 0) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ ($activeGroup ?? null) ? 6 : 7 }}" class="text-center text-muted">No contributions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
