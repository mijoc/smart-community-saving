@php
    $member = $member ?? null;
@endphp
@csrf
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Member #</label>
        <input type="text" name="member_no" value="{{ old('member_no', $member?->member_no ?? '') }}" class="form-control" placeholder="auto">
    </div>
    <div class="col-md-3">
        <label class="form-label required">First name</label>
        <input type="text" name="first_name" value="{{ old('first_name', $member?->first_name ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label required">Last name</label>
        <input type="text" name="last_name" value="{{ old('last_name', $member?->last_name ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label required">Gender</label>
        <select name="gender" class="form-select">
            @foreach(['male','female','other'] as $g)
                <option value="{{ $g }}" @selected(old('gender', $member?->gender ?? '')===$g)>{{ ucfirst($g) }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Date of birth</label>
        <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $member?->date_of_birth?->format('Y-m-d') ?? '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">National ID</label>
        <input type="text" name="national_id" value="{{ old('national_id', $member?->national_id ?? '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $member?->phone ?? '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email', $member?->email ?? '') }}" class="form-control">
    </div>

    <div class="col-12">
        <hr class="mt-2 mb-1">
        <h4 class="mb-0">Residence</h4>
        <small class="text-muted">Pick step by step — province first, then district, sector, cell and village.</small>
    </div>
    @include('partials.location_cascade', [
        'selected' => [
            'province' => isset($member) ? $member?->province_code : null,
            'district' => isset($member) ? $member?->district_code : null,
            'sector'   => isset($member) ? $member?->sector_code   : null,
            'cell'     => isset($member) ? $member?->cell_code     : null,
            'village'  => isset($member) ? $member?->village_code  : null,
        ],
    ])
    <div class="col-12">
        <label class="form-label">Address (street / house no.)</label>
        <input type="text" name="address" value="{{ old('address', $member?->address ?? '') }}" class="form-control">
    </div>

    <div class="col-md-3">
        <label class="form-label">Next of kin</label>
        <input type="text" name="next_of_kin_name" value="{{ old('next_of_kin_name', $member?->next_of_kin_name ?? '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">NoK phone</label>
        <input type="text" name="next_of_kin_phone" value="{{ old('next_of_kin_phone', $member?->next_of_kin_phone ?? '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">Occupation</label>
        <input type="text" name="occupation" value="{{ old('occupation', $member?->occupation ?? '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label required">Status</label>
        <select name="status" class="form-select">
            @foreach(['active','inactive','suspended','exited'] as $s)
                <option value="{{ $s }}" @selected(old('status', $member?->status ?? 'active')===$s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Joined on</label>
        <input type="date" name="joined_on" value="{{ old('joined_on', $member?->joined_on?->format('Y-m-d') ?? now()->toDateString()) }}" class="form-control">
    </div>
    <div class="col-md-9">
        <label class="form-label">Photo</label>
        <input type="file" name="photo" class="form-control">
    </div>

    @if(isset($groups))
    @php
        $currentMemberGroupIds = isset($member) ? $member->groups->pluck('id')->all() : [];
        $defaultIds = old('group_ids', $currentMemberGroupIds ?: (isset($defaultGroup) && $defaultGroup ? [$defaultGroup] : []));
    @endphp
    <div class="col-12">
        <label class="form-label">Add to groups</label>
        <select name="group_ids[]" multiple class="form-select" size="4">
            @foreach($groups as $g)
                <option value="{{ $g->id }}" @selected(in_array($g->id, $defaultIds))>{{ $g->name }}</option>
            @endforeach
        </select>
        <small class="text-muted">A member can belong to multiple groups. The active group is pre-selected.</small>
    </div>
    @endif

    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" rows="3" class="form-control">{{ old('notes', $member?->notes ?? '') }}</textarea>
    </div>

    @unless(isset($member) && $member?->exists)
        {{-- Login credentials (only shown when creating a new member) --}}
        <div class="col-12"><hr class="mt-2 mb-1"><h4 class="mb-0">Login account (optional)</h4>
            <small class="text-muted">Tick this to let the member sign in. They can change their password later.</small>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="create_login" value="1" id="createLogin"
                       @checked(old('create_login'))>
                <span class="form-check-label">Create login</span>
            </label>
        </div>
        <div class="col-md-4">
            <label class="form-label">Login username</label>
            <input type="text" name="login_username" value="{{ old('login_username') }}" class="form-control"
                   placeholder="member_username">
        </div>
        <div class="col-md-4">
            <label class="form-label">Recovery email</label>
            <input type="email" name="login_email" value="{{ old('login_email') }}" class="form-control"
                   placeholder="member@example.com">
        </div>
        <div class="col-md-4">
            <label class="form-label">Initial password</label>
            <input type="text" name="login_password" value="{{ old('login_password') }}" class="form-control"
                   placeholder="leave blank to auto-generate" minlength="8">
        </div>
    @endunless
</div>
