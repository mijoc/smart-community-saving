@extends('layouts.app')
@section('title','Payment Requests')
@section('content')

<x-page_header title="Payment Requests" pretitle="Contributions">
    <x-slot name="actions">
        @if(!auth()->user()->hasRole('member'))
        <div class="btn-group">
            <a href="{{ request()->fullUrlWithQuery(['status' => 'pending_review']) }}"
               class="btn btn-sm {{ request('status','pending_review') === 'pending_review' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Pending @if($pendingCount > 0)<span class="badge bg-red ms-1">{{ $pendingCount }}</span>@endif
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'approved']) }}"
               class="btn btn-sm {{ request('status') === 'approved' ? 'btn-primary' : 'btn-outline-secondary' }}">Approved</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'rejected']) }}"
               class="btn btn-sm {{ request('status') === 'rejected' ? 'btn-primary' : 'btn-outline-secondary' }}">Rejected</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => '']) }}"
               class="btn btn-sm {{ request('status') === '' ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
        </div>
        @endif
    </x-slot>
</x-page_header>

@if(session('status'))
    <div class="alert alert-success mt-3">{{ session('status') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger mt-3">{{ session('error') }}</div>
@endif

<div class="card mt-3">
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Contribution</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Pay date</th>
                    <th>Proof</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($requests as $req)
                <tr>
                    <td>
                        <div class="fw-medium">{{ $req->member?->full_name }}</div>
                        <div class="text-muted small">{{ $req->member?->member_no }}</div>
                    </td>
                    <td>
                        <a href="{{ route('contributions.show', $req->contribution_id) }}" class="text-decoration-none">
                            #{{ $req->contribution_id }}
                            @if($req->contribution)
                                <span class="text-muted small">{{ ucfirst(str_replace('_',' ',$req->contribution->type)) }}</span>
                            @endif
                        </a>
                    </td>
                    <td class="fw-medium">{{ number_format($req->amount, 0) }}</td>
                    <td>{{ ucfirst(str_replace('_',' ',$req->method)) }}</td>
                    <td class="text-muted small">{{ $req->channel_ref ?: '—' }}</td>
                    <td>{{ $req->paid_on?->format('Y-m-d') }}</td>
                    <td>
                        @if($req->proof_path)
                            @if($req->proof_is_image)
                                <a href="{{ $req->proof_url }}" target="_blank" title="View proof image">
                                    <img src="{{ $req->proof_url }}" alt="proof"
                                         style="height:40px; width:56px; object-fit:cover; border-radius:4px; border:1px solid #dee2e6">
                                </a>
                            @else
                                <a href="{{ $req->proof_url }}" target="_blank"
                                   class="btn btn-sm btn-outline-secondary py-0 px-2" title="Open PDF">
                                    <i class="ti ti-file-text me-1"></i>PDF
                                </a>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($req->isPending())
                            <span class="badge bg-yellow text-dark">Pending</span>
                        @elseif($req->isApproved())
                            <span class="badge bg-green">Approved</span>
                        @else
                            <span class="badge bg-red">Rejected</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $req->created_at->diffForHumans() }}</td>
                    <td class="text-end">
                        @if($req->isPending())
                            @can('review', App\Models\ContributionPaymentRequest::class)
                            <div class="d-flex gap-1 justify-content-end">
                                <form method="POST" action="{{ route('payment-requests.approve', $req) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success" onclick="return confirm('Approve this payment request?')">
                                        <i class="ti ti-check me-1"></i>Approve
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#rejectModal{{ $req->id }}">
                                    <i class="ti ti-x me-1"></i>Reject
                                </button>
                            </div>
                            @endcan
                        @elseif($req->isRejected())
                            <span class="text-muted small" title="{{ $req->rejection_reason }}">
                                <i class="ti ti-info-circle"></i> {{ Str::limit($req->rejection_reason, 30) }}
                            </span>
                        @elseif($req->isApproved())
                            <span class="text-muted small">by {{ $req->reviewer?->name }}</span>
                        @endif
                    </td>
                </tr>

                @if($req->isPending())
                <div class="modal fade" id="rejectModal{{ $req->id }}" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <form method="POST" action="{{ route('payment-requests.reject', $req) }}">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reject request</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label">Reason <span class="text-red">*</span></label>
                                    <textarea name="rejection_reason" class="form-control" rows="3" required
                                              placeholder="e.g. Transaction reference not found, wrong amount…"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Reject</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @endif
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No payment requests found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())
        <div class="card-footer">{{ $requests->links() }}</div>
    @endif
</div>

@endsection
