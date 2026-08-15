<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Monthly Report — {{ $group->name }} — {{ $month->format('F Y') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    <style>
        body { padding: 16px; font-size: 12px; }
        @media print {
            .no-print { display:none !important; }
            .card { box-shadow: none !important; border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body class="bg-white">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="m-0">{{ $group->name }}</h1>
            <div class="text-muted">Treasurer Summary Report — <strong>{{ $month->format('F Y') }}</strong></div>
        </div>
        <div class="no-print btn-list">
            <button class="btn btn-primary" onclick="window.print()">Print this page</button>
            <a class="btn" href="javascript:window.close()">Close</a>
        </div>
    </div>

    @include('reports.monthly._sheet', ['report' => $report, 'currency' => $report['header']['currency']])

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
