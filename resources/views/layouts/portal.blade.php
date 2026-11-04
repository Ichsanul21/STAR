<!DOCTYPE html>
<html lang="id" data-theme="honda">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#cc0000">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ $title ?? 'Dealer Portal' }} · Honda</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
@php
    $user = auth()->user();
    $unread = $user?->notifications()->whereNull('read_at')->count() ?? 0;
    $impersonator = session('dev_original_id') ? \App\Models\User::find(session('dev_original_id')) : null;
    $isImpersonating = $impersonator && $user && (int) $impersonator->id !== (int) $user->id;
@endphp

<header class="border-b bg-white" style="border-color: var(--border)">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
        <a href="{{ route('portal.index') }}" class="flex items-center gap-3">
            <div class="text-2xl font-extrabold tracking-tight" style="color: var(--brand)">Honda</div>
            <div class="hidden border-l pl-3 text-xs font-semibold sm:block" style="border-color: var(--border)">
                {{ $user?->dealer?->name ?? 'Dealer Portal' }}
            </div>
        </a>

        <nav class="ml-auto flex items-center gap-1 text-sm">
            <a href="{{ route('portal.index') }}" class="rounded-full px-4 py-2 font-semibold {{ request()->routeIs('portal.*') ? 'text-white' : 'text-slate-600' }}" @if(request()->routeIs('portal.*')) style="background: var(--brand)" @endif>Laporan</a>
            <a href="{{ route('notifications.index') }}" class="relative rounded-full px-4 py-2 font-semibold text-slate-600">Notifikasi
                @if ($unread > 0)
                    <span class="absolute -right-1 -top-1 rounded-full bg-red-600 px-1.5 py-0.5 text-[9px] font-bold text-white">{{ $unread }}</span>
                @endif
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-full px-4 py-2 font-semibold text-slate-600">Keluar</button>
            </form>
        </nav>
    </div>
</header>

@if ($isImpersonating)
    <div class="impersonation-bar">
        <span class="impersonation-dot"></span>
        <span class="min-w-0 flex-1"><b>Mode simulasi:</b> {{ $user->name }} ({{ $user->roleLabel() }})</span>
        <form method="POST" action="{{ route('dev.stop') }}">
            @csrf
            <button class="impersonation-stop" type="submit">Kembali ke Developer</button>
        </form>
    </div>
@endif

<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    @if (session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    @yield('content')
</main>
<x-preview-modal />
@stack('scripts')
</body>
</html>
