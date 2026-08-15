@php $u = auth()->user(); @endphp
<header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">

        {{-- ───── Group switcher ───── --}}
        <div class="navbar-nav flex-row order-md-first">
            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex align-items-center px-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar avatar-sm bg-primary-lt me-2"><i class="ti ti-users-group"></i></span>
                    <div class="d-none d-md-block lh-1">
                        <div class="text-muted small text-uppercase" style="font-size:.65rem">{{ __('Active group') }}</div>
                        <div class="fw-bold">
                            @if($activeGroup ?? false)
                                {{ $activeGroup->name }}
                            @else
                                <em>{{ __('All groups') }}</em>
                            @endif
                        </div>
                    </div>
                    <i class="ti ti-chevron-down ms-2"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-arrow">
                    <div class="dropdown-header text-muted">{{ __('Switch group') }}</div>
                    @forelse(($accessibleGroups ?? collect()) as $g)
                        <form method="POST" action="{{ route('groups.switch') }}" class="m-0">@csrf
                            <input type="hidden" name="group_id" value="{{ $g->id }}">
                            <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                            <button class="dropdown-item d-flex align-items-center {{ ($activeGroup?->id ?? null) === $g->id ? 'active' : '' }}">
                                <i class="ti ti-circle-{{ ($activeGroup?->id ?? null) === $g->id ? 'check-filled text-success' : 'dot text-muted' }} me-2"></i>
                                <span class="flex-grow-1">{{ $g->name }}</span>
                                <small class="text-muted ms-2">{{ $g->code }}</small>
                            </button>
                        </form>
                    @empty
                        <div class="dropdown-item text-muted">{{ __('No groups assigned') }}</div>
                    @endforelse

                    @if($u->isSuperAdmin())
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('groups.switch') }}" class="m-0">@csrf
                            <input type="hidden" name="group_id" value="">
                            <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                            <button class="dropdown-item">
                                <i class="ti ti-world me-2"></i> {{ __('All groups (global)') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- ───── Notifications bell ───── --}}
        <div class="navbar-nav flex-row order-md-last ms-auto">
            {{-- Language switcher (flag dropdown) --}}
            @include('partials.language_switcher', ['wrapperClass' => 'me-2'])

            {{-- Personal notifications bell --}}
            @if(($unreadPersonalCount ?? 0) > 0 || true)
            <div class="nav-item dropdown me-1">
                <a href="#" class="nav-link px-2 position-relative" data-bs-toggle="dropdown" title="My notifications">
                    <i class="ti ti-bell-ringing" style="font-size:1.4rem"></i>
                    @if(($unreadPersonalCount ?? 0) > 0)
                        <span class="badge bg-orange text-white position-absolute top-0 end-0 translate-middle-y rounded-pill"
                              style="font-size:.65rem">
                            {{ ($unreadPersonalCount ?? 0) > 99 ? '99+' : $unreadPersonalCount }}
                        </span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow p-0" style="width:360px">
                    <div class="d-flex align-items-center px-3 py-2 border-bottom">
                        <strong class="me-auto">
                            <i class="ti ti-bell me-1"></i>My notifications
                        </strong>
                        @if(($unreadPersonalCount ?? 0) > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}" class="m-0">@csrf
                                <button class="btn btn-link btn-sm p-0 text-muted">Mark all read</button>
                            </form>
                        @endif
                    </div>
                    <div class="list-group list-group-flush" style="max-height:380px; overflow-y:auto">
                        @forelse(($personalNotifs ?? collect()) as $n)
                            <div class="list-group-item bg-{{ $n->color ?? 'blue' }}-lt p-0">
                                <div class="d-flex align-items-start p-2">
                                    @if($n->link)
                                        <a href="{{ $n->link }}" class="d-flex align-items-start flex-fill text-reset text-decoration-none">
                                    @else
                                        <div class="d-flex align-items-start flex-fill">
                                    @endif
                                        <span class="avatar avatar-sm bg-{{ $n->color ?? 'blue' }}-lt me-2 mt-1">
                                            <i class="ti ti-{{ $n->icon ?? 'bell' }}"></i>
                                        </span>
                                        <div class="flex-fill small">
                                            <div class="fw-medium">{{ $n->title }}</div>
                                            @if($n->body)
                                                <div class="text-muted">{{ $n->body }}</div>
                                            @endif
                                            <div class="text-muted mt-1">{{ $n->created_at->diffForHumans() }}</div>
                                        </div>
                                    @if($n->link)
                                        </a>
                                    @else
                                        </div>
                                    @endif
                                    <div class="ms-2 d-flex gap-1 align-items-center">
                                        @if($n->link)
                                            <span class="btn btn-sm btn-ghost-primary py-0 px-1" title="View">
                                                <i class="ti ti-arrow-right"></i>
                                            </span>
                                        @endif
                                        <form method="POST" action="{{ route('notifications.read', $n) }}" class="m-0">@csrf
                                            <button class="btn btn-sm btn-ghost-secondary py-0 px-1" title="Dismiss">
                                                <i class="ti ti-x"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted text-center p-3">
                                <i class="ti ti-bell-off d-block mb-1" style="font-size:1.5rem"></i>
                                No new notifications.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
            @endif

            {{-- Group activity bell --}}
            <div class="nav-item dropdown me-2">
                <a href="#" class="nav-link px-2 position-relative" data-bs-toggle="dropdown" title="Group activity">
                    <i class="ti ti-activity" style="font-size:1.4rem"></i>
                    @if(($unreadActivityCount ?? 0) > 0)
                        <span class="badge bg-red text-white position-absolute top-0 end-0 translate-middle-y rounded-pill"
                              style="font-size:.65rem">
                            {{ $unreadActivityCount > 99 ? '99+' : $unreadActivityCount }}
                        </span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow p-0" style="width:360px">
                    <div class="d-flex align-items-center px-3 py-2 border-bottom">
                        <strong class="me-auto">Group activity</strong>
                        @if(($unreadActivityCount ?? 0) > 0)
                            <form method="POST" action="{{ route('activity.read') }}" class="m-0">@csrf
                                <button class="btn btn-link btn-sm p-0 text-muted">Mark all read</button>
                            </form>
                        @endif
                    </div>
                    <div class="list-group list-group-flush" style="max-height:380px; overflow-y:auto">
                        @forelse(($recentActivities ?? collect()) as $a)
                            @php $isNew = ($lastSeenAt ?? null) && $a->created_at->gt($lastSeenAt) && $a->actor_user_id !== $u->id; @endphp
                            <a href="{{ $a->url }}" class="list-group-item list-group-item-action {{ $isNew ? 'bg-blue-lt' : '' }}">
                                <div class="d-flex">
                                    <span class="avatar avatar-sm bg-{{ $a->color ?: 'blue' }}-lt me-2">
                                        <i class="ti ti-{{ $a->icon ?: 'activity' }}"></i>
                                    </span>
                                    <div class="flex-fill small">
                                        <div>
                                            <strong>{{ $a->actor->name ?? 'System' }}</strong>
                                            {{ $a->description }}
                                        </div>
                                        <div class="text-muted">
                                            {{ $a->group->name ?? '—' }} · {{ $a->created_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="text-muted text-center p-3">No group activity yet.</div>
                        @endforelse
                    </div>
                    <a href="{{ route('activity.index') }}" class="d-block text-center py-2 border-top text-decoration-none">
                        See all activity <i class="ti ti-arrow-right"></i>
                    </a>
                </div>
            </div>

            {{-- ───── User menu ───── --}}
            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown">
                    <span class="avatar avatar-sm" style="background-image: url('{{ $u->avatar_url }}')"></span>
                    <div class="d-none d-xl-block ps-2">
                        <div>{{ $u->name }}</div>
                        <div class="mt-1 small text-muted">
                            {{ $u->roles->pluck('name')->map(fn($r) => str_replace('_',' ',$r))->join(', ') ?: 'no role' }}
                        </div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="ti ti-user me-1"></i> {{ __('My profile') }}
                    </a>
                    <a href="{{ route('settings.edit') }}" class="dropdown-item">
                        <i class="ti ti-settings me-1"></i> {{ __('Settings') }}
                    </a>
                    @if(($accessibleGroups ?? collect())->count() > 1 || $u->isSuperAdmin())
                        <a href="{{ route('groups.select') }}" class="dropdown-item">
                            <i class="ti ti-arrows-shuffle me-1"></i> {{ __('Switch group') }}
                        </a>
                    @endif
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                        <button class="dropdown-item">
                            <i class="ti ti-logout me-1"></i> {{ __('Sign out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
