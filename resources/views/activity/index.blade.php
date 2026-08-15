@extends('layouts.app')
@section('title', 'Group activity')
@section('content')

<x-page_header title="Group activity" pretitle="What's happening in your groups">
    <x-slot:actions>
        <form method="POST" action="{{ route('activity.read') }}" class="m-0">@csrf
            <button class="btn btn-outline-primary"><i class="ti ti-checks me-1"></i>Mark all read</button>
        </form>
    </x-slot:actions>
</x-page_header>

<div class="card mt-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <select name="group_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All my groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected(request('group_id') == $g->id)>
                            {{ $g->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="">All event types</option>
                    @foreach([
                        'member.created' => 'Member added',
                        'member.login.created' => 'Login created',
                        'member.password.reset' => 'Password reset',
                        'contribution.created' => 'Contribution recorded',
                        'contribution.waived' => 'Contribution waived',
                        'payment.created' => 'Payment received',
                        'loan.requested' => 'Loan requested',
                        'loan.approved' => 'Loan approved',
                        'loan.rejected' => 'Loan rejected',
                        'loan.disbursed' => 'Loan disbursed',
                        'loan.repaid' => 'Loan repayment',
                    ] as $val => $label)
                        <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if(request()->hasAny(['group_id','type']))
                <div class="col-md-4"><a href="{{ route('activity.index') }}" class="btn btn-link">Clear filters</a></div>
            @endif
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        @forelse($activities as $a)
            <a href="{{ $a->url }}" class="d-flex pb-3 mb-3 text-reset text-decoration-none {{ ! $loop->last ? 'border-bottom' : '' }}">
                <span class="avatar avatar-md bg-{{ $a->color ?: 'blue' }}-lt me-3">
                    <i class="ti ti-{{ $a->icon ?: 'activity' }}" style="font-size:1.4rem"></i>
                </span>
                <div class="flex-fill">
                    <div>
                        <strong>{{ $a->actor->name ?? 'System' }}</strong>
                        {{ $a->description }}
                    </div>
                    <div class="text-muted small mt-1">
                        <i class="ti ti-users-group me-1"></i>{{ $a->group->name ?? '—' }}
                        <span class="mx-2">·</span>
                        <i class="ti ti-clock me-1"></i>{{ $a->created_at->diffForHumans() }}
                        <span class="mx-2">·</span>
                        {{ $a->created_at->format('M j, Y g:i a') }}
                    </div>
                    @if($a->data && count($a->data))
                        <div class="mt-2">
                            @foreach($a->data as $k => $v)
                                @if(is_scalar($v))
                                    <span class="badge bg-secondary-lt me-1">
                                        {{ str_replace('_',' ', $k) }}: <strong>{{ $v }}</strong>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                <i class="ti ti-arrow-up-right text-muted ms-3 mt-1" aria-hidden="true"></i>
            </a>
        @empty
            <div class="text-center text-muted py-5">
                <i class="ti ti-news" style="font-size:3rem"></i>
                <div class="mt-2">No activity yet in your groups.</div>
            </div>
        @endforelse

        {{ $activities->links() }}
    </div>
</div>
@endsection
