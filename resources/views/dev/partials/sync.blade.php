<div class="mb-3">
    <h2 class="text-xl font-extrabold">Sinkronisasi</h2>
    <p class="text-sm" style="color: var(--muted)">Cek konsistensi progress, finance, dan report antar-modul.</p>
</div>

<div class="grid gap-4 xl:grid-cols-2">
    <div class="card card-pad">
        <div class="section-title">Reconciliation</div>
        <p class="mt-1 text-xs" style="color: var(--muted)">Menjalankan `star:reconcile` dan mencatat hasilnya di activity log developer.</p>
        <form method="POST" action="{{ route('dev.reconcile') }}" class="mt-3">
            @csrf
            <button class="btn btn-primary btn-sm" type="submit">Jalankan Reconcile</button>
        </form>
        <a href="{{ route('integrity.index') }}" class="link mt-3 inline-block text-xs">Buka Integrity Dashboard</a>
    </div>

    <div class="card card-pad">
        <div class="section-title">Status antrean</div>
        <div class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">Queue driver</span><b>{{ $environment['queue'] ?? '—' }}</b></div>
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">Failed jobs</span><b style="color: {{ $failedJobs->count() > 0 ? 'var(--danger)' : 'var(--success)' }}">{{ $failedJobs->count() }}</b></div>
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">Cache</span><b>{{ $environment['cache'] ?? '—' }}</b></div>
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">Session</span><b>{{ $environment['session'] ?? '—' }}</b></div>
        </div>
    </div>
</div>
