@php
    $u        = auth()->user();
    $mine     = $m->user_id === $u->id;
    $name     = $m->user?->name ?? 'Unknown';
    $initials = collect(explode(' ', $name))->take(2)
        ->map(fn ($w) => strtoupper(substr($w, 0, 1)))->join('');
@endphp
<div class="chat-msg {{ $mine ? 'mine' : '' }}" data-id="{{ $m->id }}">
    @if($m->user?->avatar_url)
        <span class="avatar avatar-sm" style="background-image:url('{{ $m->user->avatar_url }}')"></span>
    @else
        <span class="avatar avatar-sm" style="background:#206bc4; color:#fff;">{{ $initials }}</span>
    @endif
    <div>
        <div class="meta">
            <strong>{{ $name }}</strong> · {{ $m->created_at->diffForHumans() }}
        </div>
        <div class="bubble">{{ $m->body }}</div>
    </div>
</div>
