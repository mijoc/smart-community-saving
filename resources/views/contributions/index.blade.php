@extends('layouts.app')
@section('title','Contributions')
@section('content')

<x-page_header title="Contributions" pretitle="Select a member to view their ledger">
    <x-slot name="actions">
        @include('partials._report_downloads', ['report' => 'contributions'])
        @can('create', App\Models\Contribution::class)
        <a href="{{ route('contributions.create') }}" class="btn">
            <i class="ti ti-plus me-1"></i>Manual entry
        </a>
        <a href="{{ route('payments.create') }}" class="btn btn-primary">
            <i class="ti ti-cash me-1"></i>Record payment
        </a>
        @endcan
    </x-slot>
</x-page_header>

@if(session('status'))
<div class="alert alert-success alert-dismissible mt-3" role="alert">
    {{ session('status') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($members->isEmpty())
    <div class="empty mt-4">
        <div class="empty-icon"><i class="ti ti-users-minus" style="font-size:3rem;opacity:.3"></i></div>
        <p class="empty-title">No members found</p>
        <p class="empty-subtitle text-muted">There are no members in the active group yet.</p>
    </div>
@else
<div class="row row-cards mt-3">
    @foreach($members as $member)
        @php
            $contribs      = $member->contributions;
            $paid          = $contribs->where('status','paid')->count();
            $partial       = $contribs->where('status','partial')->count();
            $pending       = $contribs->where('status','pending')->count();
            $overdue       = $contribs->where('status','overdue')->count();
            $totalExpected = (float) $contribs->sum('expected_amount');
            $totalPaid     = (float) $contribs->sum('paid_amount');
            $totalPenalty     = (float) $contribs->sum('late_fee_amount');
            $totalOutstanding = $contribs->whereNotIn('status', ['paid','waived'])
                ->sum(fn($c) => max(0, (float)$c->expected_amount + (float)$c->late_fee_amount - (float)$c->paid_amount));
            $pct           = $totalExpected > 0 ? min(100, round($totalPaid / $totalExpected * 100)) : 0;
            $barColor      = $overdue > 0 ? 'red' : ($pct >= 100 ? 'green' : 'blue');
            $hasIssues     = $overdue > 0;
        @endphp
        <div class="col-sm-6 col-lg-3">
            <a href="{{ route('contributions.index', ['member_id' => $member->id]) }}"
               class="card card-link card-link-pop h-100 text-decoration-none {{ $hasIssues ? 'border-danger' : '' }}">
                <div class="card-body d-flex flex-column align-items-center text-center p-4">

                    {{-- Avatar --}}
                    <span class="avatar avatar-xl rounded-circle mb-3"
                          style="background-image: url('{{ $member->photo_url }}')"></span>

                    {{-- Name & member number --}}
                    <div class="fw-bold fs-5 lh-sm">{{ $member->full_name }}</div>
                    <div class="text-muted small mb-3">{{ $member->member_no }}</div>

                    {{-- Status badges --}}
                    <div class="d-flex flex-wrap justify-content-center gap-1 mb-3">
                        @if($paid > 0)
                            <span class="badge bg-green-lt">
                                <i class="ti ti-check me-1"></i>{{ $paid }} paid
                            </span>
                        @endif
                        @if($partial > 0)
                            <span class="badge bg-azure-lt">
                                <i class="ti ti-circle-half me-1"></i>{{ $partial }} partial
                            </span>
                        @endif
                        @if($pending > 0)
                            <span class="badge bg-yellow-lt">
                                <i class="ti ti-clock me-1"></i>{{ $pending }} pending
                            </span>
                        @endif
                        @if($overdue > 0)
                            <span class="badge bg-red-lt">
                                <i class="ti ti-alert-triangle me-1"></i>{{ $overdue }} overdue
                            </span>
                        @endif
                        @if($contribs->isEmpty())
                            <span class="badge bg-secondary-lt">No contributions</span>
                        @endif
                    </div>

                    {{-- Progress bar --}}
                    <div class="w-100 mt-auto">
                        <div class="d-flex justify-content-between text-muted small mb-1">
                            <span>Saved</span>
                            <span>{{ $pct }}%</span>
                        </div>
                        <div class="progress mb-1" style="height:6px">
                            <div class="progress-bar bg-{{ $barColor }}" style="width:{{ $pct }}%"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted" style="font-size:.72rem">
                            <span>{{ number_format($totalPaid, 0) }} paid</span>
                            <span>{{ number_format($totalExpected, 0) }} expected</span>
                        </div>
                    </div>

                </div>
                @if($hasIssues)
                <div class="card-footer bg-red-lt py-2 text-center text-danger small fw-semibold">
                    <i class="ti ti-alert-circle me-1"></i>Has overdue contributions
                    @if($totalPenalty > 0)
                    <span class="ms-2 text-orange">· Penalty +{{ number_format($totalPenalty, 0) }}</span>
                    @endif
                    @if($totalOutstanding > 0)
                    <div class="mt-1 text-danger" style="font-size:.72rem">
                        Outstanding: <strong>{{ number_format($totalOutstanding, 0) }}</strong>
                    </div>
                    @endif
                </div>
                @endif
            </a>
        </div>
    @endforeach
</div>
@endif

@endsection
