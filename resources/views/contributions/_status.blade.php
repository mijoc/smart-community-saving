@php
$map = [
    'pending'  => ['yellow',  'Pending'],
    'partial'  => ['azure',   'Partial'],
    'paid'     => ['green',   'Paid'],
    'overdue'  => ['red',     'Overdue'],
    'waived'   => ['secondary','Waived'],
];
[$c,$l] = $map[$status] ?? ['secondary', $status];
@endphp
<span class="badge bg-{{ $c }}-lt">{{ $l }}</span>
