<!DOCTYPE html>
<html lang="id" data-theme="alenkosa">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a2540">
    @auth
        @if (config('broadcasting.default') === 'reverb')
            <meta name="user-id" content="{{ auth()->id() }}">
            <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
            <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.client.host') }}">
            <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.client.port') }}">
            <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.client.scheme') }}">
        @endif
    @endauth
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ $title ?? 'STAR' }} · STAR</title>
    <script>
        try {
            if (localStorage.getItem('star.sidebar') === 'collapsed') {
                document.documentElement.setAttribute('data-sidebar-collapsed', '1');
            }
        } catch (error) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
@php
    $user = auth()->user();
    $unread = $user?->notifications()->whereNull('read_at')->count() ?? 0;
    $myTaskCount = $user ? \App\Models\Task::where('assigned_to', $user->id)->where('status', '!=', 'done')->count() : 0;
    $pendingExpenses = $user && ! $user->isDealer()
        ? \App\Models\Expense::where('status', 'submitted')->whereHas('event', fn ($q) => $q->visibleTo($user))->count()
        : 0;
    $openConflicts = $user && ! $user->isDealer()
        ? \App\Models\ConflictReport::whereNull('resolved_at')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->count()
        : 0;

    $notificationCounts = $user
        ? \App\Models\AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
        : collect();

    $impersonator = session('dev_original_id') ? \App\Models\User::find(session('dev_original_id')) : null;
    $isImpersonating = $impersonator && $user && (int) $impersonator->id !== (int) $user->id;
    $devToolsEnabled = (bool) config('app.dev_tools');

    $notifTask = (int) $notificationCounts->get('task', 0);
    $notifFinance = (int) $notificationCounts->only(['expense', 'approval', 'payment'])->sum();
    $notifReport = (int) $notificationCounts->get('report', 0);

    $actionItems = $user && ! $user->isDealer()
        ? app(\App\Services\ActionInboxService::class)->items($user)
        : collect();

    $actionCounts = [
        'task' => $actionItems->where('category', 'task')->count(),
        'finance' => $actionItems->where('category', 'finance')->count(),
        'report' => $actionItems->where('category', 'report')->count(),
        'total' => $actionItems->count(),
    ];

    $notifTask += $actionCounts['task'];
    $notifFinance += $actionCounts['finance'];
    $notifReport += $actionCounts['report'];
    $notifTotal = $unread + $actionCounts['total'];
    $notifType = request()->routeIs('notifications.*') ? request()->string('type')->toString() : '';
@endphp

<div class="app-shell">
    <div id="sidebarOverlay" class="app-overlay hidden lg:hidden"></div>

    <aside id="sidebar" class="app-sidebar -translate-x-full lg:translate-x-0">
        <div class="sidebar-brand">
            <a href="{{ $user?->isDealer() ? route('portal.index') : route('dashboard') }}" class="brand-link">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M12 3l2.4 5.3 5.6.6-4.2 3.9 1.2 5.7L12 15.7 6.9 18.5l1.2-5.7L4 8.9l5.6-.6z"/>
                    </svg>
                </span>
                <span class="min-w-0">
                    <span class="brand-word">STAR</span>
                    <span class="brand-sub">Supported by Alenkosa</span>
                </span>
                @if ($user?->isDev())
                    <span class="dev-badge">DEV</span>
                @endif
            </a>
            <button id="toggleSidebar" class="sidebar-collapse hidden lg:grid" type="button" aria-label="Ciutkan sidebar" aria-expanded="true">
                <x-icon name="chevron" class="w-4 h-4" />
            </button>
        </div>

        <nav class="sidebar-nav">
            @if ($user?->isDealer())
                <div class="nav-section">Dealer</div>
                <a href="{{ route('portal.index') }}" class="nav-item {{ request()->routeIs('portal.*') ? 'is-active' : '' }}">
                    <x-icon name="dashboard" class="nav-icon" />
                    <span class="nav-label">Dealer Portal</span>
                </a>
                <a href="{{ route('notifications.index') }}" class="nav-item {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}">
                    <x-icon name="bell" class="nav-icon" />
                    <span class="nav-label">Notifikasi</span>
                    <span class="nav-badge {{ $unread > 0 ? '' : 'hidden' }}" data-notif-nav-badge>{{ $unread }}</span>
                </a>
            @else
                <div class="nav-section">Menu</div>
                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                    <x-icon name="dashboard" class="nav-icon" />
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="{{ route('global.my-tasks') }}" class="nav-item {{ request()->routeIs('global.my-tasks') ? 'is-active' : '' }}">
                    <x-icon name="tasks" class="nav-icon" />
                    <span class="nav-label">My Tasks</span>
                    @if ($myTaskCount > 0)<span class="nav-badge">{{ $myTaskCount }}</span>@endif
                </a>
                <a href="{{ route('events.index') }}" class="nav-item {{ request()->routeIs('events.*') ? 'is-active' : '' }}">
                    <x-icon name="events" class="nav-icon" />
                    <span class="nav-label">Events</span>
                </a>
                <a href="{{ route('clients.index') }}" class="nav-item {{ request()->routeIs('clients.*') ? 'is-active' : '' }}">
                    <x-icon name="clients" class="nav-icon" />
                    <span class="nav-label">Clients</span>
                </a>

                <div class="nav-section">Keuangan</div>
                <a href="{{ route('global.budget') }}" class="nav-item {{ request()->routeIs('global.budget') ? 'is-active' : '' }}">
                    <x-icon name="budget" class="nav-icon" />
                    <span class="nav-label">Budget</span>
                </a>
                <a href="{{ route('global.expenses') }}" class="nav-item {{ request()->routeIs('global.expenses') ? 'is-active' : '' }}">
                    <x-icon name="expenses" class="nav-icon" />
                    <span class="nav-label">Expenses</span>
                    @if ($pendingExpenses > 0)<span class="nav-badge nav-badge-warn">{{ $pendingExpenses }}</span>@endif
                </a>
                <a href="{{ route('global.payments') }}" class="nav-item {{ request()->routeIs('global.payments') ? 'is-active' : '' }}">
                    <x-icon name="payments" class="nav-icon" />
                    <span class="nav-label">Payments</span>
                </a>

                <div class="nav-section">Dokumentasi</div>
                <a href="{{ route('global.documents') }}" class="nav-item {{ request()->routeIs('global.documents') ? 'is-active' : '' }}">
                    <x-icon name="documents" class="nav-icon" />
                    <span class="nav-label">Documents</span>
                </a>
                <a href="{{ route('global.reports') }}" class="nav-item {{ request()->routeIs('global.reports') ? 'is-active' : '' }}">
                    <x-icon name="reports" class="nav-icon" />
                    <span class="nav-label">Reports</span>
                </a>

                <div class="nav-section">Sistem</div>
                <a href="{{ route('conflicts.index') }}" class="nav-item {{ request()->routeIs('conflicts.*') ? 'is-active' : '' }}">
                    <x-icon name="conflicts" class="nav-icon" />
                    <span class="nav-label">Konflik Data</span>
                    @if ($openConflicts > 0)<span class="nav-badge nav-badge-warn">{{ $openConflicts }}</span>@endif
                </a>
                <a href="{{ route('offline.index') }}" class="nav-item {{ request()->routeIs('offline.*') ? 'is-active' : '' }}">
                    <x-icon name="offline" class="nav-icon" />
                    <span class="nav-label">Offline Queue</span>
                    <span class="nav-badge hidden" data-offline-nav-badge><span data-offline-count>0</span></span>
                </a>

                @if ($user?->isAdmin())
                    <div class="nav-section">Admin</div>
                    <a href="{{ route('global.master-data') }}" class="nav-item {{ request()->routeIs('global.master-data') ? 'is-active' : '' }}">
                        <x-icon name="master" class="nav-icon" />
                        <span class="nav-label">Master Data</span>
                    </a>
                    <a href="{{ route('activity-logs.index') }}" class="nav-item {{ request()->routeIs('activity-logs.*') ? 'is-active' : '' }}">
                        <x-icon name="audit" class="nav-icon" />
                        <span class="nav-label">Activity Log</span>
                    </a>
                @endif
                @if ($user?->isAdmin() || $user?->isFinance())
                    <a href="{{ route('integrity.index') }}" class="nav-item {{ request()->routeIs('integrity.*') ? 'is-active' : '' }}">
                        <x-icon name="integrity" class="nav-icon" />
                        <span class="nav-label">Integrity</span>
                    </a>
                @endif

                @if ($user?->isDev() && $devToolsEnabled)
                    <div class="nav-section">Developer</div>
                    <a href="{{ route('dev.index') }}" class="nav-item {{ request()->routeIs('dev.*') ? 'is-active' : '' }}">
                        <x-icon name="master" class="nav-icon" />
                        <span class="nav-label">Dev Tools</span>
                    </a>
                @endif

                <div class="nav-section">Umum</div>
                <a href="{{ route('global.settings') }}" class="nav-item {{ request()->routeIs('global.settings*') ? 'is-active' : '' }}">
                    <x-icon name="settings" class="nav-icon" />
                    <span class="nav-label">Settings</span>
                </a>
            @endif
        </nav>

        <div class="sidebar-scroll-track" data-sidebar-scroll-track hidden>
            <span class="sidebar-scroll-thumb" data-sidebar-scroll-thumb></span>
        </div>

        @if (! $user?->isDealer())
            <div class="sidebar-notif">
                <div class="sidebar-notif-head">
                    <span class="sidebar-notif-title">Notifikasi</span>
                    <span class="sidebar-notif-total" data-notif-total>{{ $notifTotal }}</span>
                </div>
                <a href="{{ route('notifications.index', ['type' => 'task']) }}" class="sidebar-notif-row {{ $notifType === 'task' ? 'is-active' : '' }}">
                    <span>Tugas</span>
                    <span class="sidebar-notif-count" data-notif-count="task">{{ $notifTask }}</span>
                </a>
                <a href="{{ route('notifications.index', ['type' => 'finance']) }}" class="sidebar-notif-row {{ $notifType === 'finance' ? 'is-active' : '' }}">
                    <span>Keuangan</span>
                    <span class="sidebar-notif-count" data-notif-count="finance">{{ $notifFinance }}</span>
                </a>
                <a href="{{ route('notifications.index', ['type' => 'report']) }}" class="sidebar-notif-row {{ $notifType === 'report' ? 'is-active' : '' }}">
                    <span>Laporan</span>
                    <span class="sidebar-notif-count" data-notif-count="report">{{ $notifReport }}</span>
                </a>
                <a href="{{ route('notifications.index') }}" class="sidebar-notif-all">Lihat semua notifikasi</a>
            </div>

            <button type="button" class="sidebar-notif-mini" data-notif-trigger data-placement="right" aria-label="Notifikasi" aria-expanded="false" aria-controls="notificationPopover">
                <x-icon name="bell" class="w-5 h-5" />
                <span class="sidebar-mini-badge {{ $notifTotal > 0 ? '' : 'hidden' }}" data-notif-mini>{{ $notifTotal }}</span>
            </button>
        @endif

        <div class="sidebar-user">
            <div class="sidebar-avatar">{{ strtoupper(substr($user?->name ?? 'S', 0, 1)) }}</div>
            <div class="min-w-0 flex-1">
                <div class="sidebar-user-name">{{ $user?->name }}</div>
                <div class="sidebar-user-role">{{ $user?->roleLabel() }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="icon-btn" type="submit" title="Keluar" aria-label="Keluar">
                    <x-icon name="logout" class="w-4 h-4" />
                </button>
            </form>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button id="openSidebar" class="icon-btn lg:hidden" type="button" aria-label="Buka menu">
                <x-icon name="menu" class="w-5 h-5" />
            </button>

            <form action="{{ route('global.search') }}" class="topbar-search" role="search">
                <x-icon name="search" class="topbar-search-icon" />
                <input id="globalSearch" name="q" value="{{ request()->routeIs('global.search') ? request('q') : '' }}" placeholder="Cari event, dealer, task, invoice…" autocomplete="off">
                <span class="topbar-kbd">Ctrl K</span>
            </form>

            <div class="topbar-actions">
                <span data-offline-badge class="hidden badge badge-warning">Offline · <span data-offline-count>0</span></span>
                <span data-offline-conflict class="hidden badge badge-danger">Konflik</span>
                @if ($user?->isDev() && $devToolsEnabled)
                    <a href="{{ route('dev.index') }}" class="topbar-dev" title="Dev Tools">DEV</a>
                @endif
                <div class="topbar-user">
                    <div class="topbar-avatar">{{ strtoupper(substr($user?->name ?? 'S', 0, 1)) }}</div>
                    <div class="hidden min-w-0 sm:block">
                        <div class="topbar-name">{{ $user?->name }}</div>
                        <div class="topbar-email">{{ $user?->email }}</div>
                    </div>
                </div>
            </div>
        </header>

        @if ($isImpersonating)
            <div class="impersonation-bar">
                <span class="impersonation-dot"></span>
                <span class="min-w-0 flex-1">
                    <b>Mode simulasi:</b> {{ $user->name }} ({{ $user->roleLabel() }})
                    <span class="hidden sm:inline" style="opacity:.75">· semua aksi tercatat sebagai user ini</span>
                </span>
                <form method="POST" action="{{ route('dev.stop') }}">
                    @csrf
                    <button class="impersonation-stop" type="submit">Kembali ke Developer</button>
                </form>
            </div>
        @endif

        <main class="app-content">
            @if (session('success'))
                <div data-toast class="mb-4 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
                    <span class="mt-0.5">✓</span>
                    <span class="flex-1">{{ session('success') }}</span>
                    <button type="button" class="text-green-700/70 hover:text-green-900" data-toast-close aria-label="Tutup">✕</button>
                </div>
            @endif

            @if (session('error'))
                <div data-toast class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                    <span class="mt-0.5">!</span>
                    <span class="flex-1">{{ session('error') }}</span>
                    <button type="button" class="text-red-700/70 hover:text-red-900" data-toast-close aria-label="Tutup">✕</button>
                </div>
            @endif

            @if ($errors->has('conflict'))
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <span class="flex-1">
                        <span class="font-bold">Konflik data.</span>
                        {{ $errors->first('conflict') }}
                        @if (session('conflict_id'))
                            <a href="{{ route('conflicts.show', session('conflict_id')) }}" class="link font-semibold">Lihat & merge konflik →</a>
                        @endif
                    </span>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.location.reload()">Muat Ulang</button>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <div class="font-bold">Periksa input:</div>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            @continue($errors->has('conflict') && $error === $errors->first('conflict'))
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@auth
    @if ($user?->isDealer())
        <nav class="app-bottomnav lg:hidden">
            <div class="grid grid-cols-2">
                <a href="{{ route('portal.index') }}" class="bottomnav-item {{ request()->routeIs('portal.*') ? 'is-active' : '' }}">
                    <x-icon name="dashboard" class="w-5 h-5" /> Portal
                </a>
                <a href="{{ route('notifications.index') }}" class="bottomnav-item {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}">
                    <x-icon name="bell" class="w-5 h-5" /> Notifikasi
                </a>
            </div>
        </nav>
    @else
        <nav class="app-bottomnav lg:hidden">
            <div class="grid grid-cols-5">
                @php
                    $bottomNav = [
                        ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Home'],
                        ['route' => 'events.index', 'match' => 'events.*', 'icon' => 'events', 'label' => 'Events'],
                        ['route' => 'global.my-tasks', 'match' => 'global.my-tasks', 'icon' => 'tasks', 'label' => 'Tasks'],
                        ['route' => 'global.expenses', 'match' => 'global.expenses', 'icon' => 'expenses', 'label' => 'Finance'],
                        ['route' => 'clients.index', 'match' => 'clients.*', 'icon' => 'clients', 'label' => 'Clients'],
                    ];
                @endphp
                @foreach ($bottomNav as $item)
                    <a href="{{ route($item['route']) }}" class="bottomnav-item {{ request()->routeIs($item['match']) ? 'is-active' : '' }}">
                        <x-icon :name="$item['icon']" class="w-5 h-5" /> {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>
    @endif
@endauth

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebarNav = document.querySelector('.sidebar-nav');
    const root = document.documentElement;

    const ensureActiveVisible = (behavior = 'smooth') => {
        if (!sidebarNav) return;
        const active = sidebarNav.querySelector('.nav-item.is-active');
        if (!active) return;

        const navBox = sidebarNav.getBoundingClientRect();
        const activeBox = active.getBoundingClientRect();

        if (activeBox.top < navBox.top + 4 || activeBox.bottom > navBox.bottom - 4) {
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            sidebarNav.scrollTo({
                top: sidebarNav.scrollTop + (activeBox.top - navBox.top) - 12,
                behavior: reduced || behavior === 'auto' ? 'auto' : 'smooth',
            });
        }
    };

    const sidebarScrollTrack = document.querySelector('[data-sidebar-scroll-track]');
    const sidebarScrollThumb = document.querySelector('[data-sidebar-scroll-thumb]');

    const updateSidebarScrollbar = () => {
        if (!sidebarNav || !sidebarScrollTrack || !sidebarScrollThumb) return;

        const scrollable = sidebarNav.scrollHeight > sidebarNav.clientHeight + 2;

        if (!scrollable) {
            sidebarScrollTrack.hidden = true;

            return;
        }

        sidebarScrollTrack.hidden = false;

        const sidebarBox = sidebar.getBoundingClientRect();
        const navBox = sidebarNav.getBoundingClientRect();

        sidebarScrollTrack.style.top = `${navBox.top - sidebarBox.top}px`;
        sidebarScrollTrack.style.height = `${sidebarNav.clientHeight}px`;

        const thumbHeight = Math.max(18, sidebarNav.clientHeight * (sidebarNav.clientHeight / sidebarNav.scrollHeight));
        const maxScroll = Math.max(1, sidebarNav.scrollHeight - sidebarNav.clientHeight);
        const thumbTop = (sidebarNav.scrollTop / maxScroll) * (sidebarNav.clientHeight - thumbHeight);

        sidebarScrollThumb.style.height = `${thumbHeight}px`;
        sidebarScrollThumb.style.top = `${thumbTop}px`;
    };

    sidebarNav?.addEventListener('scroll', updateSidebarScrollbar, { passive: true });

    sidebarNav?.addEventListener('mouseover', (event) => {
        if (event.target.closest('.nav-item')) {
            sidebarScrollTrack?.classList.add('is-visible');
        }
    });
    sidebarNav?.addEventListener('mouseout', (event) => {
        if (event.target.closest('.nav-item') && !event.relatedTarget?.closest('.nav-item')) {
            sidebarScrollTrack?.classList.remove('is-visible');
        }
    });
    sidebar?.addEventListener('transitionend', (event) => {
        if (event.propertyName === 'width') {
            updateSidebarScrollbar();
        }
    });

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        setTimeout(() => {
            ensureActiveVisible('auto');
            updateSidebarScrollbar();
        }, 220);
    };
    const close = () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    };
    document.getElementById('openSidebar')?.addEventListener('click', open);
    overlay?.addEventListener('click', close);

    // Sidebar collapse / expand (desktop only)
    const toggleSidebar = document.getElementById('toggleSidebar');
    let sidebarToggleToken = 0;
    const syncToggleLabel = () => {
        const collapsed = root.hasAttribute('data-sidebar-collapsed');
        if (!toggleSidebar) return;
        toggleSidebar.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggleSidebar.setAttribute('aria-label', collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar');
    };

    toggleSidebar?.addEventListener('click', () => {
        const isCollapsing = !root.hasAttribute('data-sidebar-collapsed');
        const desktop = window.innerWidth >= 1024;
        const from = desktop ? Math.round(sidebar.getBoundingClientRect().width) : 0;

        if (desktop) {
            sidebar.getAnimations().forEach((animation) => animation.cancel());
            sidebar.style.width = `${from}px`;
        }

        root.toggleAttribute('data-sidebar-collapsed', isCollapsing);

        try {
            localStorage.setItem('star.sidebar', isCollapsing ? 'collapsed' : 'expanded');
        } catch (error) {}

        if (desktop) {
            const to = isCollapsing ? 42 : 188;
            const token = ++sidebarToggleToken;
            sidebar.style.transition = 'none';

            const finishAnimation = () => {
                if (token !== sidebarToggleToken) return;
                animation.cancel();
                sidebar.style.width = '';
                sidebar.style.transition = '';
                updateSidebarScrollbar();
                ensureActiveVisible('auto');
            };

            const animation = sidebar.animate(
                [{ width: `${from}px` }, { width: `${to}px` }],
                { duration: 280, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
            );

            animation.onfinish = finishAnimation;
            setTimeout(finishAnimation, 340);
        }

        syncToggleLabel();
        hideTooltip();
        ensureActiveVisible('auto');
        setTimeout(updateSidebarScrollbar, 60);
        setTimeout(() => {
            updateSidebarScrollbar();
            ensureActiveVisible('auto');
        }, 320);
    });
    syncToggleLabel();

    // Tooltip for collapsed sidebar
    const tooltip = document.createElement('div');
    tooltip.className = 'sidebar-tooltip';
    tooltip.hidden = true;
    document.body.appendChild(tooltip);

    const hideTooltip = () => {
        tooltip.hidden = true;
    };

    const showTooltip = (element) => {
        if (!root.hasAttribute('data-sidebar-collapsed') || window.innerWidth < 1024) return;

        const label = element.querySelector('.nav-label')?.textContent?.trim()
            || element.dataset.tooltip
            || element.getAttribute('aria-label');

        if (!label) return;

        const rect = element.getBoundingClientRect();
        tooltip.textContent = label;
        tooltip.hidden = false;
        tooltip.style.left = `${rect.right + 10}px`;
        tooltip.style.top = `${rect.top + rect.height / 2}px`;
    };

    document.addEventListener('mouseover', (event) => {
        const target = event.target.closest('.nav-item, .sidebar-notif-mini, .sidebar-collapse, .sidebar-user .icon-btn');
        if (target) showTooltip(target);
    });
    document.addEventListener('mouseout', (event) => {
        if (event.target.closest('.nav-item, .sidebar-notif-mini, .sidebar-collapse, .sidebar-user .icon-btn')) hideTooltip();
    });
    sidebarNav?.addEventListener('scroll', hideTooltip);
    window.addEventListener('scroll', hideTooltip, true);
    window.addEventListener('resize', hideTooltip);
    window.addEventListener('resize', updateSidebarScrollbar);

    // Notification popover
    const popover = document.getElementById('notificationPopover');
    const triggers = [...document.querySelectorAll('[data-notif-trigger]')];

    const closeNotif = () => {
        if (!popover || popover.hidden) return;
        popover.classList.remove('is-open');
        popover.hidden = true;
        triggers.forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));
    };

    const openNotif = (trigger) => {
        if (!popover) return;

        triggers.forEach((item) => item.setAttribute('aria-expanded', 'false'));
        popover.hidden = false;
        popover.classList.add('is-open');

        const rect = trigger.getBoundingClientRect();
        const width = popover.offsetWidth;
        const height = popover.offsetHeight;
        const mobile = window.innerWidth < 1024;

        let left;
        let top;

        if (mobile || trigger.dataset.placement !== 'right') {
            left = Math.min(Math.max(12, rect.right - width), window.innerWidth - width - 12);
            top = rect.bottom + 8;

            if (top + height > window.innerHeight - 12) {
                top = Math.max(12, rect.top - height - 8);
            }
        } else {
            left = rect.right + 12;
            if (left + width > window.innerWidth - 12) {
                left = Math.max(12, rect.left - width - 12);
            }
            top = Math.min(Math.max(12, rect.top - 8), window.innerHeight - height - 12);
        }

        popover.style.left = `${left}px`;
        popover.style.top = `${top}px`;
        trigger.setAttribute('aria-expanded', 'true');
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-notif-trigger]');
        if (trigger) {
            event.preventDefault();
            if (popover && !popover.hidden && trigger.getAttribute('aria-expanded') === 'true') {
                closeNotif();
            } else {
                openNotif(trigger);
            }
            return;
        }

        if (popover && !popover.hidden && !popover.contains(event.target)) {
            closeNotif();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeNotif();
            hideTooltip();
        }
    });

    window.addEventListener('resize', closeNotif);
    window.addEventListener('scroll', (event) => {
        const target = event.target;
        if (target === document || target === document.documentElement || target === document.body || target === sidebarNav) {
            closeNotif();
        }
    }, true);

    ensureActiveVisible('auto');
    updateSidebarScrollbar();

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            document.getElementById('globalSearch')?.focus();
        }
    });
});
</script>
@auth
    <div id="notificationPopover" class="notif-popover" role="dialog" aria-label="Notifikasi" hidden>
        <div class="notif-popover-head">
            <div class="notif-popover-title">Notifikasi <span class="notif-popover-total" data-notif-total>{{ $notifTotal }}</span></div>
        </div>

        @if (! $user?->isDealer())
            <div class="notif-popover-list">
                <a href="{{ route('notifications.index', ['type' => 'task']) }}" class="notif-popover-row {{ $notifType === 'task' ? 'is-active' : '' }}">
                    <span>Tugas</span>
                    <b data-notif-count="task">{{ $notifTask }}</b>
                </a>
                <a href="{{ route('notifications.index', ['type' => 'finance']) }}" class="notif-popover-row {{ $notifType === 'finance' ? 'is-active' : '' }}">
                    <span>Keuangan</span>
                    <b data-notif-count="finance">{{ $notifFinance }}</b>
                </a>
                <a href="{{ route('notifications.index', ['type' => 'report']) }}" class="notif-popover-row {{ $notifType === 'report' ? 'is-active' : '' }}">
                    <span>Laporan</span>
                    <b data-notif-count="report">{{ $notifReport }}</b>
                </a>
            </div>
        @endif

        <a href="{{ route('notifications.index') }}" class="notif-popover-footer">Lihat semua notifikasi</a>
    </div>
@endauth

@auth
    @php($uiWatermark = app(\App\Services\UiWatermarkService::class)->payload(auth()->user(), request()))
    @if ($uiWatermark['enabled'])
        <x-ui-watermark :line1="$uiWatermark['line1']" :line2="$uiWatermark['line2']" :opacity="$uiWatermark['opacity']" :color="$uiWatermark['color']" :speed="$uiWatermark['speed']" />
    @endif
@endauth

<x-preview-modal />
@stack('scripts')
</body>
</html>
