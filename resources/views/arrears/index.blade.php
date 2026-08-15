@extends('layouts.app')
@section('title','Arrears')
@section('content')

<x-page_header title="Open arrears" pretitle="Late & overdue">
    <x-slot name="actions">
        @include('partials._report_downloads', ['report' => 'arrears'])
        @if(auth()->user()->hasAnyRole(['super_admin','group_admin','treasurer','secretary']))
        <form method="POST" action="{{ route('arrears.run') }}" class="d-inline"
              onsubmit="return confirm('{{ __('Calculate late fees for overdue contributions now?') }}')">
            @csrf
            <input type="hidden" name="group_id" value="{{ request('group_id') }}">
            <button class="btn btn-outline-warning"
                    title="{{ __('Manual run. The automatic daily engine still runs at 00:30.') }}">
                <i class="ti ti-calculator me-1"></i>{{ __('Calculate late fees') }}
            </button>
        </form>
        @endif
    </x-slot>
</x-page_header>

@if(auth()->user()->hasRole('member'))
    @php $isGroupView = request('view') === 'group'; @endphp
    <div class="btn-group mt-3" role="group">
        <a href="{{ route('arrears.index') }}" class="btn btn-sm {{ $isGroupView ? 'btn-outline-primary' : 'btn-primary' }}">My arrears</a>
        <a href="{{ route('arrears.index', ['view' => 'group']) }}" class="btn btn-sm {{ $isGroupView ? 'btn-primary' : 'btn-outline-primary' }}">Group arrears</a>
    </div>
@endif

<div class="card mt-3">
    <div class="card-body border-bottom py-3">
        <form class="row g-2" method="GET">
            <div class="col-md-4"><select name="group_id" class="form-select"><option value="">All groups</option>
                @foreach($groups as $g)<option value="{{ $g->id }}" @selected(request('group_id')==$g->id)>{{ $g->name }}</option>@endforeach
            </select></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">Any status</option>
                @foreach(['open','partially_cleared','cleared','waived'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="ti ti-search"></i></button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead><tr><th>Member</th><th>Group</th><th>Type</th><th>Period</th><th>Days overdue</th><th class="text-end">Late fees</th><th class="text-end">Outstanding</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($arrears as $a)
                <tr>
                    <td><a href="{{ route('members.show', $a->member_id) }}" class="text-reset">{{ $a->member?->full_name }}</a></td>
                    <td>{{ $a->group?->name }}</td>
                    <td>{{ str_replace('_',' ',$a->contribution?->type) }}</td>
                    <td class="text-muted">{{ $a->contribution?->period_start?->format('Y-m-d') }}</td>
                    <td><span class="badge bg-red-lt">{{ $a->days_overdue }}d</span></td>
                    <td class="text-end">{{ number_format($a->late_fee_applied, 0) }}</td>
                    <td class="text-end"><strong>{{ number_format($a->outstanding_amount, 0) }}</strong></td>
                    <td>
                        @php $color = ['open'=>'red','partially_cleared'=>'yellow','cleared'=>'green','waived'=>'secondary'][$a->status] ?? 'secondary'; @endphp
                        <span class="badge bg-{{ $color }}-lt">{{ ucfirst(str_replace('_',' ',$a->status)) }}</span>
                    </td>
                    <td class="text-end">
                        @can('create', App\Models\Contribution::class)
                        <a href="{{ route('payments.create', ['contribution_id' => $a->contribution_id]) }}" class="btn btn-sm btn-primary"><i class="ti ti-cash"></i></a>
                        @endcan
                        @unless(auth()->user()->hasRole('member'))
                            @if($a->status === 'open')
                            <form method="POST" action="{{ route('arrears.waive', $a) }}" class="d-inline" onsubmit="return confirm('Waive this arrear?')">@csrf
                                <button class="btn btn-sm btn-outline-warning"><i class="ti ti-circle-off"></i></button>
                            </form>
                            @endif
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No arrears.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $arrears->links() }}</div>
</div>
@endsection
