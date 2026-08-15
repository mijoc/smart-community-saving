@extends('layouts.app')
@section('title','Schedules')
@section('content')
<x-page_header :title="$group->name.' · Contribution schedules'" pretitle="Groups">
    <x-slot name="actions">
        @can('update', $group)
        @php $dueRule = $group->rule('contribution_due', 'today'); @endphp
        <form method="POST" action="{{ route('groups.schedules.catchup', $group) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-success"
                onclick="return confirm('Run catch-up for all schedules in this group?\n\nMissing contribution rows will be generated up to the configured due date ({{ $dueRule }}).\n\nContinue?')"
                title="Generate all missing contribution periods for every active schedule in this group">
                <i class="ti ti-clock-play me-1"></i>Catch up missed periods
            </button>
        </form>
        @endcan
        <a href="{{ route('groups.schedules.create', $group) }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>New schedule</a>
    </x-slot>
</x-page_header>

<div class="card mt-3">
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead><tr><th>Name</th><th>Type</th><th>Frequency</th><th class="text-end">Amount</th><th>Start</th><th>Next due</th><th>Late fee</th><th></th></tr></thead>
            <tbody>
                @forelse($schedules as $s)
                <tr>
                    <td><strong>{{ $s->name }}</strong></td>
                    <td>{{ ucfirst(str_replace('_',' ',$s->type)) }}</td>
                    <td>{{ ucfirst($s->frequency) }}</td>
                    <td class="text-end">{{ number_format($s->amount, 0) }} {{ $group->currency }}</td>
                    <td class="text-muted">{{ $s->start_date->format('Y-m-d') }}</td>
                    <td class="text-muted">{{ $s->next_due_on?->format('Y-m-d') ?? '—' }}</td>
                    <td class="text-muted small">{{ $s->late_fee_pct }}% / {{ number_format($s->late_fee_flat, 0) }}</td>
                    <td class="text-end">
                        <div class="btn-list">
                            <form method="POST" action="{{ route('groups.schedules.generate', [$group,$s]) }}">@csrf
                                <button class="btn btn-sm btn-outline-primary" title="Generate next contributions"><i class="ti ti-bolt"></i></button>
                            </form>
                            <a href="{{ route('groups.schedules.edit', [$group,$s]) }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-edit"></i></a>
                            <form method="POST" action="{{ route('groups.schedules.destroy', [$group,$s]) }}">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete schedule?')"><i class="ti ti-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No schedules yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
