@php $u = auth()->user(); @endphp
<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <h1 class="navbar-brand navbar-brand-autodark">
            <a href="{{ route('dashboard') }}" class="text-decoration-none text-white d-flex align-items-center gap-2">
                @php $sysLogo = \App\Models\SystemSetting::publicUrl(\App\Models\SystemSetting::get('app_logo')); $sysName = \App\Models\SystemSetting::get('app_name', config('app.name')); @endphp
                @if($sysLogo)
                    <img src="{{ $sysLogo }}" alt="{{ $sysName }}" style="height:32px;max-width:120px;object-fit:contain;">
                @else
                    <i class="ti ti-coin"></i>
                @endif
                @if($activeGroup ?? false)
                    {{ $activeGroup->name }}
                @else
                    {{ $sysName }}
                @endif
            </a>
        </h1>

        <div class="collapse navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav pt-lg-3">
                <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('dashboard') }}">
                        <span class="nav-link-icon"><i class="ti ti-layout-dashboard"></i></span>
                        <span class="nav-link-title">{{ __('Dashboard') }}</span>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('activity.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('activity.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-news"></i></span>
                        <span class="nav-link-title">
                            {{ __('Activity') }}
                            @if(($unreadActivityCount ?? 0) > 0)
                                <span class="badge bg-red text-white ms-2">{{ $unreadActivityCount }}</span>
                            @endif
                        </span>
                    </a>
                </li>

                @if($activeGroup ?? false)
                <li class="nav-item {{ request()->routeIs('chatboard.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('chatboard.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-messages"></i></span>
                        <span class="nav-link-title">
                            {{ __('Chatboard') }}
                            @if(($unreadChatCount ?? 0) > 0)
                                <span class="badge bg-red text-white ms-2">{{ $unreadChatCount }}</span>
                            @endif
                        </span>
                    </a>
                </li>
                @endif

                @can('viewAny', \App\Models\Member::class)
                <li class="nav-item {{ request()->routeIs('members.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('members.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-users"></i></span>
                        <span class="nav-link-title">{{ __('Members') }}</span>
                    </a>
                </li>
                @endcan

                @can('viewAny', \App\Models\Group::class)
                <li class="nav-item {{ request()->routeIs('groups.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('groups.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-users-group"></i></span>
                        <span class="nav-link-title">{{ __('Groups') }}</span>
                    </a>
                </li>
                @endcan

                @can('viewAny', \App\Models\Contribution::class)
                <li class="nav-item {{ request()->routeIs('contributions.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('contributions.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-clipboard-list"></i></span>
                        <span class="nav-link-title">{{ __('Contributions') }}</span>
                    </a>
                </li>
                @if($activeGroup ?? false)
                <li class="nav-item {{ request()->routeIs('groups.schedules.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('groups.schedules.index', $activeGroup) }}">
                        <span class="nav-link-icon"><i class="ti ti-calendar-repeat"></i></span>
                        <span class="nav-link-title">{{ __('Schedules') }}</span>
                    </a>
                </li>
                @endif
                <li class="nav-item {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('payments.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-cash"></i></span>
                        <span class="nav-link-title">{{ __('Payments') }}</span>
                    </a>
                </li>
                <li class="nav-item {{ request()->routeIs('payment-requests.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('payment-requests.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-clock-check"></i></span>
                        <span class="nav-link-title">
                            {{ __('Pay Requests') }}
                            @if(($pendingPaymentRequests ?? 0) > 0)
                                <span class="badge bg-yellow text-dark ms-2">{{ $pendingPaymentRequests }}</span>
                            @endif
                        </span>
                    </a>
                </li>
                <li class="nav-item {{ request()->routeIs('arrears.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('arrears.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-alert-triangle"></i></span>
                        <span class="nav-link-title">{{ __('Arrears') }}</span>
                    </a>
                </li>
                @endcan

                <li class="nav-item {{ request()->routeIs('passbooks.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('passbooks.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-book"></i></span>
                        <span class="nav-link-title">{{ __('Passbooks') }}</span>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('treasury.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('treasury.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-building-bank"></i></span>
                        <span class="nav-link-title">{{ __('Treasury') }}</span>
                    </a>
                </li>

                @can('viewAny', \App\Models\CashbookEntry::class)
                <li class="nav-item {{ request()->routeIs('cashbook.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('cashbook.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-wallet"></i></span>
                        <span class="nav-link-title">{{ __('Cashbook') }}</span>
                    </a>
                </li>
                @endcan

                @can('viewAny', \App\Models\Meeting::class)
                <li class="nav-item {{ request()->routeIs('meetings.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('meetings.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-calendar-event"></i></span>
                        <span class="nav-link-title">{{ __('Attendance') }}</span>
                    </a>
                </li>
                @endcan

                @can('viewAny', \App\Models\Rotation::class)
                    @php
                        // Hide the Rotations menu when the active group has
                        // turned rotations off in its rules. With no active
                        // group (super-admin global view) we keep it visible.
                        $rotationsEnabled = ! $activeGroup
                            || (bool) $activeGroup->rule('rotation_enabled', true);
                    @endphp
                    @if ($rotationsEnabled)
                        <li class="nav-item {{ request()->routeIs('rotations.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('rotations.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-rotate-clockwise"></i></span>
                                <span class="nav-link-title">{{ __('Rotations') }}</span>
                            </a>
                        </li>
                    @endif
                @endcan

                @can('viewAny', \App\Models\Loan::class)
                <li class="nav-item {{ request()->routeIs('loans.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('loans.index') }}">
                        <span class="nav-link-icon"><i class="ti ti-cash-banknote"></i></span>
                        <span class="nav-link-title">
                            Loans
                            @if($u && $u->isSuperAdmin() === false && ! $u->hasRole('member'))
                                {{-- show pending count badge for staff --}}
                            @endif
                        </span>
                    </a>
                </li>
                @endcan

                <li class="nav-item {{ request()->routeIs('reports.group_loans*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('reports.group_loans') }}">
                        <span class="nav-link-icon"><i class="ti ti-report-analytics"></i></span>
                        <span class="nav-link-title">{{ __('Group Loans') }}</span>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('reports.monthly*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('reports.monthly') }}">
                        <span class="nav-link-icon"><i class="ti ti-report-money"></i></span>
                        <span class="nav-link-title">{{ __('Monthly Report') }}</span>
                    </a>
                </li>

                @if($u && $u->canManageUsers())
                    <li class="nav-header"><span class="nav-header-text">{{ __('System') }}</span></li>
                    <li class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('users.index') }}">
                            <span class="nav-link-icon"><i class="ti ti-user-shield"></i></span>
                            <span class="nav-link-title">{{ __('Users & Roles') }}</span>
                        </a>
                    </li>
                    @if($u->isSuperAdmin())
                    <li class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('settings.system') }}">
                            <span class="nav-link-icon"><i class="ti ti-settings"></i></span>
                            <span class="nav-link-title">{{ __('System Settings') }}</span>
                        </a>
                    </li>
                    @endif
                @endif
            </ul>
        </div>
    </div>
</aside>
