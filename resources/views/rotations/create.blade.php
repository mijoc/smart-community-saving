@extends('layouts.app')
@section('title', 'New rotation')
@section('content')

<x-page_header title="New rotation" pretitle="Merry-go-round payouts"></x-page_header>

<form method="POST" action="{{ route('rotations.store') }}" class="card mt-3">
    @csrf

    @if($errors->any())
        <div class="alert alert-danger m-3">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required">Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', 'Weekly merry-go-round') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label required">Group</label>
                <select name="group_id" class="form-select" required>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected(old('group_id', session('active_group_id'))==$g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label required">Cadence</label>
                <select name="frequency" class="form-select" required>
                    @foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $k=>$v)
                        <option value="{{ $k }}" @selected(old('frequency','weekly')===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label required">Recipients per turn</label>
                <input type="number" name="recipients_per_turn" class="form-control" min="1" max="50"
                       value="{{ old('recipients_per_turn', 1) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label required">Starts on</label>
                <input type="date" name="starts_on" class="form-control"
                       value="{{ old('starts_on', now()->toDateString()) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label required">Disbursement method</label>
                <select name="disbursement_method" id="disbursement_method" class="form-select" required onchange="toggleRule()">
                    <option value="full"        @selected(old('disbursement_method','full')==='full')>Full cash on hand</option>
                    <option value="percentage"  @selected(old('disbursement_method')==='percentage')>Percentage of cash on hand</option>
                    <option value="fixed"       @selected(old('disbursement_method')==='fixed')>Fixed amount per turn</option>
                </select>
            </div>

            <div class="col-md-6" id="row-pct" style="display:none">
                <label class="form-label">Percentage of cash on hand</label>
                <div class="input-group">
                    <input type="number" step="0.01" name="disbursement_pct" class="form-control"
                           value="{{ old('disbursement_pct', 50) }}">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-6" id="row-fixed" style="display:none">
                <label class="form-label">Fixed amount per turn</label>
                <input type="number" step="0.01" name="disbursement_amount" class="form-control"
                       value="{{ old('disbursement_amount') }}">
            </div>

            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
            </div>

            <div class="col-12">
                <label class="form-label required">Recipient list (drag-free, just pick the order)</label>
                <p class="text-muted small mb-2">
                    Members will receive payouts in the order shown below. Each member receives once per cycle.
                    When everyone has received, the rotation is marked complete.
                </p>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header py-2"><strong>Available members</strong>
                                <input type="search" id="memberFilter" class="form-control form-control-sm float-end" style="width:60%" placeholder="Filter…" oninput="filterMembers(this.value)">
                            </div>
                            <div class="list-group list-group-flush" id="availableList" style="max-height:340px;overflow:auto">
                                @foreach($members as $m)
                                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center member-row"
                                            data-id="{{ $m->id }}" data-label="{{ $m->full_name }} ({{ $m->member_no }})"
                                            onclick="addMember(this)">
                                        <span>{{ $m->full_name }} <span class="text-muted small">{{ $m->member_no }}</span></span>
                                        <i class="ti ti-plus text-primary"></i>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header py-2"><strong>Rotation order</strong>
                                <span class="text-muted small float-end">Use ↑ ↓ to reorder, × to remove</span>
                            </div>
                            <ol class="list-group list-group-flush" id="selectedList" style="min-height:80px"></ol>
                            <div id="hiddenInputs"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer text-end">
        <a href="{{ route('rotations.index') }}" class="btn btn-link">Cancel</a>
        <button type="submit" class="btn btn-primary">Create rotation</button>
    </div>
</form>

<script>
function toggleRule() {
    const m = document.getElementById('disbursement_method').value;
    document.getElementById('row-pct').style.display   = m === 'percentage' ? 'block' : 'none';
    document.getElementById('row-fixed').style.display = m === 'fixed'      ? 'block' : 'none';
}
toggleRule();

function filterMembers(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#availableList .member-row').forEach(el => {
        el.style.display = el.dataset.label.toLowerCase().includes(q) ? '' : 'none';
    });
}

function addMember(btn) {
    btn.remove();
    const ol = document.getElementById('selectedList');
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center';
    li.dataset.id = btn.dataset.id;
    li.dataset.label = btn.dataset.label;
    li.innerHTML = `
        <span><span class="badge bg-blue-lt me-2 pos">${ol.children.length+1}</span>${btn.dataset.label}</span>
        <span>
            <button type="button" class="btn btn-sm btn-link p-0 me-2" onclick="moveItem(this,-1)" title="Move up">↑</button>
            <button type="button" class="btn btn-sm btn-link p-0 me-2" onclick="moveItem(this,1)"  title="Move down">↓</button>
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeItem(this)" title="Remove">×</button>
        </span>`;
    ol.appendChild(li);
    rebuildHidden();
}
function moveItem(btn, dir) {
    const li = btn.closest('li');
    const sibling = dir === -1 ? li.previousElementSibling : li.nextElementSibling;
    if (sibling) li.parentNode.insertBefore(dir === -1 ? li : sibling, dir === -1 ? sibling : li);
    renumber(); rebuildHidden();
}
function removeItem(btn) {
    const li = btn.closest('li');
    const back = document.createElement('button');
    back.type = 'button';
    back.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center member-row';
    back.dataset.id = li.dataset.id; back.dataset.label = li.dataset.label;
    back.innerHTML = `<span>${li.dataset.label}</span><i class="ti ti-plus text-primary"></i>`;
    back.onclick = () => addMember(back);
    document.getElementById('availableList').appendChild(back);
    li.remove(); renumber(); rebuildHidden();
}
function renumber() {
    document.querySelectorAll('#selectedList li .pos').forEach((el,i)=>el.textContent = i+1);
}
function rebuildHidden() {
    const box = document.getElementById('hiddenInputs');
    box.innerHTML = '';
    document.querySelectorAll('#selectedList li').forEach(li => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'member_ids[]'; inp.value = li.dataset.id;
        box.appendChild(inp);
    });
}
</script>

@endsection
