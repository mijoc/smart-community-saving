@php
    $bottomItems = [];

    if (auth()->check()) {
        $bottomItems[] = [
            'route'  => 'dashboard',
            'match'  => 'dashboard',
            'icon'   => 'layout-dashboard',
            'label'  => 'Home',
            'show'   => true,
        ];
        $bottomItems[] = [
            'route'  => 'members.index',
            'match'  => 'members.*',
            'icon'   => 'users',
            'label'  => 'Members',
            'show'   => auth()->user()->can('viewAny', \App\Models\Member::class),
        ];
        $bottomItems[] = [
            'route'  => 'contributions.index',
            'match'  => 'contributions.*',
            'icon'   => 'clipboard-list',
            'label'  => 'Contrib.',
            'show'   => auth()->user()->can('viewAny', \App\Models\Contribution::class),
        ];
        $bottomItems[] = [
            'route'  => 'loans.index',
            'match'  => 'loans.*',
            'icon'   => 'cash-banknote',
            'label'  => 'Loans',
            'show'   => auth()->user()->can('viewAny', \App\Models\Loan::class),
        ];
        $bottomItems[] = [
            'route'  => 'chatboard.index',
            'match'  => 'chatboard.*',
            'icon'   => 'messages',
            'label'  => 'Chat',
            'show'   => (bool) session('active_group_id'),
        ];
    }

    $bottomItems = array_values(array_filter($bottomItems, fn ($i) => $i['show']));
@endphp

@if (count($bottomItems))
    <nav class="bottom-nav d-lg-none d-print-none" aria-label="Mobile primary">
        @foreach ($bottomItems as $item)
            @php $active = request()->routeIs($item['match']); @endphp
            <a href="{{ route($item['route']) }}"
               class="bottom-nav-item {{ $active ? 'active' : '' }}">
                <i class="ti ti-{{ $item['icon'] }}"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
@endif
