@php
    /**
     * Reusable "Download" dropdown for any report listing page.
     * Pass:
     *   $report     - the report key registered on /reports/{report}/{format}
     *   $params     - assoc array of filters to forward (defaults to current request query)
     *   $label      - optional button label (defaults to "Download")
     *   $btnClass   - optional Bootstrap class (defaults to btn-outline-primary)
     *
     * Visibility rule: staff (super_admin, group_admin, treasurer, secretary)
     * always see the button. A user who is *only* a member never does, even
     * if a page they can reach happens to include this partial.
     */
    $report   = $report   ?? null;
    $params   = $params   ?? request()->query();
    $label    = $label    ?? 'Download';
    $btnClass = $btnClass ?? 'btn-outline-primary';

    $u = auth()->user();
    $canDownload = $u && $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary']);
@endphp

@if($report && $canDownload)
<div class="dropdown d-inline-block">
    <button class="btn {{ $btnClass }} dropdown-toggle" type="button"
            data-bs-toggle="dropdown" aria-expanded="false">
        <i class="ti ti-download me-1"></i>{{ $label }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ route('reports.export', array_merge(['report' => $report, 'format' => 'pdf'],  $params)) }}">
            <i class="ti ti-file-type-pdf text-danger me-2"></i>PDF document
        </a></li>
        <li><a class="dropdown-item" href="{{ route('reports.export', array_merge(['report' => $report, 'format' => 'xlsx'], $params)) }}">
            <i class="ti ti-file-type-xls text-success me-2"></i>Excel spreadsheet
        </a></li>
        <li><a class="dropdown-item" href="{{ route('reports.export', array_merge(['report' => $report, 'format' => 'docx'], $params)) }}">
            <i class="ti ti-file-type-doc text-primary me-2"></i>Word document
        </a></li>
        <li><a class="dropdown-item" href="{{ route('reports.export', array_merge(['report' => $report, 'format' => 'csv'],  $params)) }}">
            <i class="ti ti-file-type-csv text-warning me-2"></i>CSV file
        </a></li>
    </ul>
</div>
@endif
