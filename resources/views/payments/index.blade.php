@extends('layouts.app')
@section('title','Payments')
@section('content')

<x-page_header title="Payments" pretitle="Select a member to view their history">
    <x-slot name="actions">
        @include('partials._report_downloads', ['report' => 'payments'])
        @can('create', App\Models\Contribution::class)
        <a href="{{ route('payments.bulk') }}" class="btn btn-outline-primary">
            <i class="ti ti-users-group me-1"></i>Bulk collection
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
            $pmts        = $member->payments;
            $count       = $pmts->count();
            $totalPaid   = (float) $pmts->sum('amount');
            $lastPaid    = $pmts->sortByDesc('paid_on')->first()?->paid_on;
            $methods     = $pmts->groupBy('method')->map->count();
            $topMethod   = $methods->sortDesc()->keys()->first();
        @endphp
        <div class="col-sm-6 col-lg-3">
            <a href="{{ route('payments.index', ['member_id' => $member->id]) }}"
               class="card card-link card-link-pop h-100 text-decoration-none">
                <div class="card-body d-flex flex-column align-items-center text-center p-4">

                    {{-- Avatar --}}
                    <span class="avatar avatar-xl rounded-circle mb-3"
                          style="background-image: url('{{ $member->photo_url }}')"></span>

                    {{-- Name & member number --}}
                    <div class="fw-bold fs-5 lh-sm">{{ $member->full_name }}</div>
                    <div class="text-muted small mb-3">{{ $member->member_no }}</div>

                    {{-- KPI tiles --}}
                    <div class="row g-2 w-100 mb-3">
                        <div class="col-6">
                            <div class="bg-blue-lt rounded p-2">
                                <div class="fw-bold text-blue fs-4">{{ $count }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ Str::plural('Payment', $count) }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-green-lt rounded p-2">
                                <div class="fw-bold text-green" style="font-size:.95rem">
                                    {{ number_format($totalPaid, 0) }}
                                </div>
                                <div class="text-muted" style="font-size:.72rem">Total paid</div>
                            </div>
                        </div>
                    </div>

                    {{-- Last payment & top method --}}
                    <div class="mt-auto w-100 text-start">
                        @if($lastPaid)
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Last payment</span>
                            <span class="small fw-semibold">
                                {{ \Carbon\Carbon::parse($lastPaid)->format('d M Y') }}
                            </span>
                        </div>
                        @endif
                        @if($topMethod)
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Usual method</span>
                            <span class="badge bg-blue-lt">{{ ucfirst(str_replace('_',' ',$topMethod)) }}</span>
                        </div>
                        @endif
                        @if($count === 0)
                        <div class="text-center text-muted small">No payments recorded yet</div>
                        @endif
                    </div>

                </div>
            </a>
        </div>
    @endforeach
</div>
@endif

@endsection
