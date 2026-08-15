@extends('layouts.app')
@section('title', 'Chatboard')
@section('content')

<x-page_header
    title="Chatboard"
    pretitle="{{ $activeGroup?->name ?? 'Group' }} discussion">
</x-page_header>

<div class="card mt-3">
    <div class="card-body p-0 d-flex flex-column" style="height: calc(100vh - 230px); min-height: 480px;">

        {{-- Messages list --}}
        <div id="chat-stream"
             class="flex-fill p-3 overflow-auto"
             data-poll-url="{{ route('chatboard.poll') }}"
             data-last-id="{{ $lastId }}">

            @forelse($messages as $m)
                @include('chatboard._message', ['m' => $m])
            @empty
                <div id="chat-empty" class="text-center text-muted py-5">
                    <i class="ti ti-messages" style="font-size:3rem"></i>
                    <div class="mt-2">No messages yet — be the first to say hello.</div>
                </div>
            @endforelse
        </div>

        {{-- Composer --}}
        <form id="chat-form" method="POST" action="{{ route('chatboard.store') }}"
              class="border-top p-2 d-flex gap-2 align-items-end bg-light">
            @csrf
            <textarea name="body" rows="1" maxlength="2000" required
                      placeholder="Write a message…"
                      class="form-control"
                      style="resize:none; max-height:140px;"
                      id="chat-body"></textarea>
            <button class="btn btn-primary" type="submit">
                <i class="ti ti-send"></i>
                <span class="d-none d-md-inline ms-1">Send</span>
            </button>
        </form>
    </div>
</div>

@push('head')
<style>
    .chat-msg { display:flex; margin-bottom:.85rem; align-items:flex-start; }
    .chat-msg .avatar { flex:0 0 auto; }
    .chat-msg .bubble {
        background:#f1f5f9;
        border-radius:14px;
        padding:.55rem .85rem;
        max-width: 78%;
        word-wrap: break-word;
        white-space: pre-wrap;
        font-size:.95rem;
        position:relative;
    }
    .chat-msg .meta { font-size:.72rem; color:#6c757d; margin-bottom:.15rem; }
    .chat-msg.mine { flex-direction: row-reverse; }
    .chat-msg.mine .bubble { background:#dbeafe; }
    .chat-msg.mine .meta   { text-align:right; }
    .chat-msg.mine .avatar { margin-left:.6rem; margin-right:0; }
    .chat-msg .avatar      { margin-right:.6rem; }
    .chat-msg .delete-btn  {
        opacity:0; transition:opacity .15s;
        position:absolute; top:-8px; right:-8px;
        background:#fff; border:1px solid #e5e7eb;
        border-radius:50%; width:22px; height:22px;
        line-height:20px; text-align:center;
        font-size:11px; color:#6c757d; cursor:pointer;
    }
    .chat-msg:hover .delete-btn { opacity:1; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const stream  = document.getElementById('chat-stream');
    const form    = document.getElementById('chat-form');
    const input   = document.getElementById('chat-body');
    const csrf    = document.querySelector('meta[name="csrf-token"]').content;
    const pollUrl = stream.dataset.pollUrl;

    const scrollToBottom = () => { stream.scrollTop = stream.scrollHeight; };
    scrollToBottom();

    function escapeHtml(s) {
        return (s ?? '').toString()
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function renderMessage(m) {
        const initials = (m.user.name || '?').split(' ').slice(0,2)
            .map(s => s.charAt(0).toUpperCase()).join('');
        const avatarStyle = m.user.avatar_url
            ? `background-image:url('${escapeHtml(m.user.avatar_url)}')`
            : 'background:#206bc4;color:#fff;';
        const mine = m.is_mine ? 'mine' : '';
        return `
        <div class="chat-msg ${mine}" data-id="${m.id}">
            <span class="avatar avatar-sm" style="${avatarStyle}">${m.user.avatar_url ? '' : escapeHtml(initials)}</span>
            <div>
                <div class="meta"><strong>${escapeHtml(m.user.name)}</strong> · ${escapeHtml(m.human_time)}</div>
                <div class="bubble">${escapeHtml(m.body)}</div>
            </div>
        </div>`;
    }

    function appendMessages(list) {
        if (!list.length) return;
        const empty = document.getElementById('chat-empty');
        if (empty) empty.remove();

        const wasNearBottom = (stream.scrollHeight - stream.scrollTop - stream.clientHeight) < 80;

        list.forEach(m => {
            if (stream.querySelector(`[data-id="${m.id}"]`)) return;
            stream.insertAdjacentHTML('beforeend', renderMessage(m));
            const id = parseInt(m.id, 10);
            if (id > parseInt(stream.dataset.lastId, 10)) stream.dataset.lastId = id;
        });

        if (wasNearBottom) scrollToBottom();
    }

    async function poll() {
        try {
            const r = await fetch(`${pollUrl}?after_id=${stream.dataset.lastId}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!r.ok) return;
            const data = await r.json();
            appendMessages(data.messages || []);
        } catch (_) { /* swallow */ }
    }

    setInterval(poll, 4000);

    // Auto-grow textarea
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 140) + 'px';
    });

    // Submit on Enter (Shift+Enter = newline)
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;

        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        try {
            const r = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
                credentials: 'same-origin',
            });
            if (r.ok) {
                const data = await r.json();
                if (data.message) appendMessages([data.message]);
                input.value = '';
                input.style.height = 'auto';
                scrollToBottom();
            }
        } finally {
            btn.disabled = false;
            input.focus();
        }
    });
})();
</script>
@endpush

@endsection
