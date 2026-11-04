@extends('layouts.app')

@section('title', 'Integrity')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Integrity Dashboard</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Cek konsistensi progress, budget, invoice, file, dan struktur event.</p>
    </div>
    <form method="POST" action="{{ route('artisan.reconcile') }}">
        @csrf
        <button class="btn btn-primary" type="submit">Jalankan Reconcile</button>
    </form>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Danger</div><div class="kpi-value mt-1 text-red-700">{{ $danger }}</div></div>
    <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Warning</div><div class="kpi-value mt-1 text-amber-700">{{ $warning }}</div></div>
    <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Total Issue</div><div class="kpi-value mt-1">{{ $issues->count() }}</div></div>
</div>

<div class="card mt-6 overflow-hidden">
    <div class="border-b px-5 py-4" style="border-color: var(--border)">
        <div class="section-title">Temuan</div>
        <div class="text-xs" style="color: var(--muted)">Data yang tidak sinkron atau file yang hilang.</div>
    </div>
    <div class="divide-y" style="border-color: var(--border)">
        @forelse ($issues as $issue)
            <div class="flex flex-wrap items-start gap-3 p-4 sm:p-5">
                <span class="badge {{ $issue['severity'] === 'danger' ? 'badge-danger' : 'badge-warning' }}">{{ $issue['severity'] }}</span>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--muted)">{{ $issue['type'] }}</div>
                    <div class="mt-1 text-sm font-medium">{{ $issue['message'] }}</div>
                    @if ($issue['event'])
                        <div class="mt-1 text-xs" style="color: var(--muted)">{{ $issue['event']->name }} · {{ $issue['event']->code }}</div>
                    @endif
                </div>
                @if ($issue['url'])
                    <a href="{{ $issue['url'] }}" class="btn btn-secondary btn-sm">Buka</a>
                @endif
            </div>
        @empty
            <div class="p-6"><x-empty title="Semua sinkron" body="Tidak ada mismatch progress, budget, invoice, file, atau struktur event." /></div>
        @endforelse
    </div>
</div>
@endsection
