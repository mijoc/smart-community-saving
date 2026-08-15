@csrf
<div class="row g-3">
    <div class="col-md-6"><label class="form-label required">Name</label>
        <input name="name" value="{{ old('name', $user->name ?? '') }}" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label required">Username</label>
        <input name="username" value="{{ old('username', $user->username ?? '') }}" class="form-control" required autocomplete="username"></div>
    <div class="col-md-6"><label class="form-label required">Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="form-control" required></div>

    <div class="col-md-4"><label class="form-label">Phone</label>
        <input name="phone" value="{{ old('phone', $user->phone ?? '') }}" class="form-control"></div>

    <div class="col-md-4"><label class="form-label">{{ isset($user) ? 'New password (leave blank to keep)' : 'Password' }}</label>
        <input type="password" name="password" class="form-control" {{ isset($user) ? '' : 'required' }}></div>

    <div class="col-md-4"><label class="form-label">Linked member</label>
        <select name="member_id" class="form-select">
            <option value="">—</option>
            @foreach($members as $m)
                <option value="{{ $m->id }}" @selected(old('member_id', $user->member_id ?? null) == $m->id)>{{ $m->full_name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-12"><label class="form-label">Roles</label>
        <div class="row">
        @foreach($roles as $r)
            <div class="col-md-3">
                <label class="form-check">
                    <input type="checkbox" name="roles[]" value="{{ $r->name }}" class="form-check-input"
                           @checked(in_array($r->name, old('roles', isset($user) ? $user->roles->pluck('name')->all() : [])))>
                    <span class="form-check-label">{{ str_replace('_',' ',$r->name) }}</span>
                </label>
            </div>
        @endforeach
        </div>
    </div>

    <div class="col-md-3"><label class="form-check form-switch mt-4">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
        <span class="form-check-label">Active</span>
    </label></div>
</div>
