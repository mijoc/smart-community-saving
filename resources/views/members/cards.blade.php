@extends('layouts.app')
@section('title', __('Member ID cards'))
@section('content')

<style>
    /* On-screen page */
    .cards-toolbar { margin-bottom: 1rem; }

    /* Card sheet — paper-like background, centered */
    .cards-sheet {
        background: #fff;
        padding: 12mm;
        border-radius: 8px;
        box-shadow: 0 2px 12px rgba(0,0,0,.08);
    }

    /* Card grid: 2 cards per row, ID-1 size (85.6mm × 53.98mm). */
    .id-cards { display: grid; grid-template-columns: repeat(2, 86mm); gap: 6mm; justify-content: center; }
    .id-card  {
        width: 86mm; height: 54mm;
        border: 1px solid #d0d5dd; border-radius: 4mm;
        background: #fff; color: #1f2937;
        display: grid; grid-template-rows: 9mm 1fr 6mm;
        overflow: hidden; position: relative;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,.02);
        font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        page-break-inside: avoid;
    }

    /* Top band: group name + group code */
    .id-card .band {
        background: linear-gradient(90deg, #1d4ed8 0%, #4338ca 100%);
        color: #fff;
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 3mm; font-size: 9pt; font-weight: 600;
    }
    .id-card .band .brand { display:flex; align-items:center; gap: 1.5mm; }
    .id-card .band .brand i { font-size: 11pt; }
    .id-card .band .code  { font-size: 7.5pt; opacity: .9; letter-spacing: .3px; }

    /* Body: photo | details | qr */
    .id-card .body {
        display: grid; grid-template-columns: 22mm 1fr 18mm;
        gap: 2.5mm; padding: 2.5mm 3mm;
    }
    .id-card .photo {
        width: 22mm; height: 28mm;
        border: 1px solid #e5e7eb; border-radius: 1.5mm;
        background-size: cover; background-position: center; background-color: #f3f4f6;
    }
    .id-card .details { display: flex; flex-direction: column; gap: .8mm; min-width: 0; }
    .id-card .details .name { font-size: 11pt; font-weight: 700; line-height: 1.15; color: #111827;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .id-card .details .role { font-size: 7.5pt; color: #4338ca; text-transform: uppercase; font-weight: 600; letter-spacing: .3px; }
    .id-card .details .row  { font-size: 7.5pt; color: #374151; display: flex; gap: 1mm; line-height: 1.2; }
    .id-card .details .row .label { color: #6b7280; min-width: 14mm; }
    .id-card .details .row .value { font-weight: 600; color: #111827; word-break: break-word; }
    .id-card .qr { display:flex; flex-direction:column; align-items:center; justify-content:center; gap: 1mm; }
    .id-card .qr img { width: 18mm; height: 18mm; }
    .id-card .qr .num { font-size: 7pt; font-weight: 700; color:#111827; letter-spacing:.5px; }

    /* Footer strip with status pill + caption */
    .id-card .foot {
        background: #f9fafb; border-top: 1px solid #eef0f3;
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 3mm; font-size: 7pt; color: #6b7280;
    }
    .id-card .foot .pill {
        padding: 0.6mm 2mm; border-radius: 999px; font-size: 6.5pt; font-weight: 700;
        text-transform: uppercase; letter-spacing: .4px;
    }
    .id-card .pill.active    { background:#dcfce7; color:#15803d; }
    .id-card .pill.inactive  { background:#e5e7eb; color:#374151; }
    .id-card .pill.suspended { background:#fee2e2; color:#b91c1c; }
    .id-card .pill.exited    { background:#fef3c7; color:#92400e; }

    /* Print styles: A4, hide chrome, no card shadow, no toolbar */
    @media print {
        @page { size: A4; margin: 10mm; }
        body  { background: #fff !important; }
        .navbar, header, aside, .navbar-vertical, .footer, .cards-toolbar { display: none !important; }
        .page-wrapper, .container-xl { padding: 0 !important; margin: 0 !important; max-width: none !important; }
        .cards-sheet { padding: 0; box-shadow: none; border-radius: 0; }
        .id-cards    { gap: 4mm; }
        .id-card     { box-shadow: none; }
    }
</style>

<div class="cards-toolbar d-print-none">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ url()->previous() }}" class="btn">
            <i class="ti ti-arrow-left me-1"></i> {{ __('Back') }}
        </a>
        <h2 class="h3 mb-0 me-auto">
            <i class="ti ti-id me-1 text-primary"></i>
            @if($single)
                {{ __('Member ID card') }}
            @else
                {{ __('Member ID cards') }}
                <span class="badge bg-primary-lt ms-2">{{ $members->count() }}</span>
            @endif
            @if($activeGroup)
                <span class="text-muted small">· {{ $activeGroup->name }}</span>
            @endif
        </h2>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="ti ti-printer me-1"></i> {{ __('Print') }}
        </button>
    </div>
    @if(! $single && $members->isEmpty())
        <div class="alert alert-info mt-3 mb-0">
            {{ __('No members match your current filters.') }}
        </div>
    @elseif(! $single)
        <p class="text-muted small mt-2 mb-0">
            {{ __('Tip: choose "Save as PDF" in your browser print dialog to export the cards.') }}
        </p>
    @endif
</div>

<div class="cards-sheet">
    <div class="id-cards">
        @foreach($members as $m)
            @php
                // Pick the group most relevant to this card. Prefer the
                // active group if the member belongs to it, otherwise show
                // their first group.
                $g = $activeGroup
                    ? ($m->groups->firstWhere('id', $activeGroup->id) ?? $m->groups->first())
                    : $m->groups->first();
                $position = $g?->pivot?->position ?? 'member';
                $shares   = $g?->pivot?->share_count;
                // QR encodes a verifiable text payload: group code + member no.
                $qrPayload = trim(($g->code ?? '').' / '.$m->member_no);
                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=0&data='.urlencode($qrPayload);
                $statusClass = in_array($m->status, ['active','inactive','suspended','exited'])
                    ? $m->status : 'inactive';
            @endphp

            <div class="id-card">
                <div class="band">
                    <span class="brand">
                        <i class="ti ti-coin"></i>
                        <span>{{ $g->name ?? config('app.name') }}</span>
                    </span>
                    @if($g?->code)
                        <span class="code">{{ $g->code }}</span>
                    @endif
                </div>

                <div class="body">
                    <div class="photo" style="background-image:url('{{ $m->photo_url }}')"></div>

                    <div class="details">
                        <div class="name">{{ $m->full_name }}</div>
                        <div class="role">{{ __(ucfirst(str_replace('_',' ',$position))) }}</div>

                        <div class="row">
                            <span class="label">{{ __('Member') }}</span>
                            <span class="value">{{ $m->member_no }}</span>
                        </div>
                        @if($m->phone)
                        <div class="row">
                            <span class="label">{{ __('Phone') }}</span>
                            <span class="value">{{ $m->phone }}</span>
                        </div>
                        @endif
                        @if($m->joined_on)
                        <div class="row">
                            <span class="label">{{ __('Joined') }}</span>
                            <span class="value">{{ \Illuminate\Support\Carbon::parse($m->joined_on)->format('Y-m-d') }}</span>
                        </div>
                        @endif
                        @if($shares)
                        <div class="row">
                            <span class="label">{{ __('Shares') }}</span>
                            <span class="value">{{ $shares }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="qr">
                        <img src="{{ $qrUrl }}" alt="QR" loading="lazy">
                        <span class="num">{{ $m->member_no }}</span>
                    </div>
                </div>

                <div class="foot">
                    <span>{{ __('Member ID') }}</span>
                    <span class="pill {{ $statusClass }}">{{ __(ucfirst($m->status)) }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
