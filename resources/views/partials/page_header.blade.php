@props(['title','pretitle'=>null,'actions'=>null])
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            @if($pretitle)<div class="page-pretitle text-muted small text-uppercase">{{ $pretitle }}</div>@endif
            <h2 class="page-title">{{ $title }}</h2>
        </div>
        @if($actions)<div class="col-auto ms-auto d-print-none">{{ $actions }}</div>@endif
    </div>
</div>
