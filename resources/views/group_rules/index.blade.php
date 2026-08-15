@extends('layouts.app')
@section('title','Group rules')
@section('content')

<x-page_header :title="$group->name.' · Rules'" pretitle="Groups"></x-page_header>

<div class="row row-cards mt-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Existing rules</h3></div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr><th>Key</th><th>Label</th><th>Type</th><th>Value</th><th></th></tr></thead>
                    <tbody>
                        @forelse($rules as $r)
                        <tr>
                            <td>
                                <code>{{ $r->key }}</code>
                                @if($r->description)
                                    <br><small class="text-muted">{{ $r->description }}</small>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('groups.rules.update', [$group, $r]) }}" class="d-flex gap-2 align-items-center">
                                    @csrf @method('PUT')
                                    <input name="label" value="{{ $r->label }}" class="form-control form-control-sm">
                            </td>
                            <td>
                                    <select name="type" class="form-select form-select-sm">
                                        @foreach(config('vsla.rule_types') as $k => $v)
                                            <option value="{{ $k }}" @selected($r->type===$k)>{{ $v }}</option>
                                        @endforeach
                                    </select>
                            </td>
                            <td>
                                @if($r->type === 'boolean')
                                    <label class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" name="value" value="1"
                                            @checked(filter_var($r->value, FILTER_VALIDATE_BOOL))
                                            onchange="this.form.submit()">
                                        <span class="form-check-label small">
                                            {{ filter_var($r->value, FILTER_VALIDATE_BOOL) ? __('Enabled') : __('Disabled') }}
                                        </span>
                                    </label>
                                @else
                                    <input name="value" value="{{ $r->value }}" class="form-control form-control-sm">
                                @endif
                            </td>
                            <td class="text-end">
                                    <button class="btn btn-sm btn-primary"><i class="ti ti-device-floppy"></i></button>
                                </form>
                                @unless($r->is_system)
                                <form method="POST" action="{{ route('groups.rules.destroy', [$group, $r]) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this rule?')"><i class="ti ti-trash"></i></button>
                                </form>
                                @endunless
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">No rules defined.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <form method="POST" action="{{ route('groups.rules.store', $group) }}" class="card">@csrf
            <div class="card-header"><h3 class="card-title">Add / update rule</h3></div>
            <div class="card-body">
                <div class="mb-2"><label class="form-label">Key</label><input name="key" class="form-control" required placeholder="e.g. share_value"></div>
                <div class="mb-2"><label class="form-label">Label</label><input name="label" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        @foreach(config('vsla.rule_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="mb-2"><label class="form-label">Value</label><input name="value" class="form-control"></div>
                <div class="mb-2"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control"></textarea></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Save rule</button></div>
        </form>
    </div>
</div>
@endsection
