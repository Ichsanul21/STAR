<div class="mb-3">
    <h2 class="text-xl font-extrabold">Maintenance</h2>
    <p class="text-sm" style="color: var(--muted)">Bersihkan cache, jalankan rekonsiliasi, dan reset database demo.</p>
</div>

<div class="grid gap-4 xl:grid-cols-2">
    <div class="card card-pad">
        <div class="section-title">Cache & rekonsiliasi</div>
        <p class="mt-1 text-xs" style="color: var(--muted)">Gunakan setelah deploy atau saat data terlihat tidak sinkron.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('dev.cache') }}">@csrf<button class="btn btn-secondary btn-sm" type="submit">Clear Cache</button></form>
            <form method="POST" action="{{ route('dev.reconcile') }}">@csrf<button class="btn btn-secondary btn-sm" type="submit">Run Reconcile</button></form>
            <a href="{{ route('integrity.index') }}" class="btn btn-secondary btn-sm">Integrity Dashboard</a>
        </div>
    </div>

    <div class="card card-pad">
        <div class="section-title">Reset database</div>
        <p class="mt-1 text-xs" style="color: var(--muted)">Kedua aksi akan logout dan menjalankan migrate:fresh.</p>

        <form method="POST" action="{{ route('dev.reset.demo') }}" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3" onsubmit="return confirm('Reseed seluruh data demo?')">
            @csrf
            <div class="text-sm font-bold text-amber-900">Reseed Demo</div>
            <div class="mt-1 text-xs text-amber-800">Kembalikan seluruh data demo (3 event, user, transaksi).</div>
            <div class="mt-2 flex flex-wrap items-end gap-2">
                <input name="confirmation" class="input max-w-40" placeholder="RESEED" required>
                <button class="btn btn-secondary btn-sm" type="submit" @disabled(! config('app.dev_reset'))>Reseed</button>
            </div>
        </form>

        <form method="POST" action="{{ route('dev.reset.empty') }}" class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3" onsubmit="return confirm('Hapus SEMUA data dan mulai dari nol?')">
            @csrf
            <div class="text-sm font-bold text-red-900">Hapus Total</div>
            <div class="mt-1 text-xs text-red-800">Wipe semua data, hanya menyisakan akun Developer.</div>
            <div class="mt-2 flex flex-wrap items-end gap-2">
                <input name="confirmation" class="input max-w-40" placeholder="DELETE ALL" required>
                <button class="btn btn-primary btn-sm" type="submit" @disabled(! config('app.dev_reset'))>Hapus Total</button>
            </div>
        </form>
    </div>
</div>
