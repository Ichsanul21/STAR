@extends('layouts.app')

@section('title', 'Dev Tools')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <div class="text-sm font-semibold" style="color: var(--muted)">Developer workspace</div>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Dev Tools</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Diagnostik, impersonation, dan reset database untuk pengujian.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('dev.cache') }}">@csrf<button class="btn btn-secondary" type="submit">Clear Cache</button></form>
        <form method="POST" action="{{ route('dev.reconcile') }}">@csrf<button class="btn btn-secondary" type="submit">Run Reconcile</button></form>
    </div>
</div>

<div class="grid gap-4 xl:grid-cols-4">
    @foreach ($environment as $key => $value)
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">{{ str_replace('_', ' ', $key) }}</div>
            <div class="mt-1 truncate text-sm font-bold" title="{{ $value }}">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-4">
    @foreach ($counts as $label => $total)
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">{{ str_replace('_', ' ', $label) }}</div>
            <div class="mt-1 text-2xl font-extrabold">{{ number_format($total) }}</div>
        </div>
    @endforeach
</div>

<div class="mt-4 card card-pad">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="section-title">Watermark UI & PDF</div>
            <div class="mt-1 text-xs" style="color: var(--muted)">
                Watermark diagonal untuk halaman aplikasi dan PDF report. PDF memakai teks statis saat report digenerate.
            </div>
        </div>
        <span class="badge badge-brand">{{ $watermarkSettings['enabled'] ? 'Aktif' : 'Nonaktif' }}</span>
    </div>

    <form method="POST" action="{{ route('dev.watermark') }}" class="mt-4 space-y-3">
        @csrf
        <input type="hidden" name="enabled" value="0">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="enabled" value="1" @checked($watermarkSettings['enabled'])>
            <span>Aktifkan watermark</span>
        </label>

        <div class="grid gap-3 lg:grid-cols-2">
            <div>
                <label class="label" for="watermarkLine1">Baris 1</label>
                <input id="watermarkLine1" name="line1" class="input" maxlength="160" required value="{{ old('line1', $watermarkSettings['line1']) }}">
            </div>
            <div>
                <label class="label" for="watermarkLine2">Baris 2</label>
                <input id="watermarkLine2" name="line2" class="input" maxlength="200" required value="{{ old('line2', $watermarkSettings['line2']) }}">
            </div>
        </div>

        <div class="rounded-xl px-3 py-2 text-[11px]" style="background: var(--brand-soft); color: var(--muted)">
            Token: <span class="font-mono">{ip}</span>, <span class="font-mono">{device}</span>,
            <span class="font-mono">{timestamp}</span>, <span class="font-mono">{user}</span>.
            Alias: <span class="font-mono">$IP_Address</span>, <span class="font-mono">$Devices</span>,
            <span class="font-mono">$Timestamps</span>, <span class="font-mono">$User</span>.
        </div>

        <div class="rounded-xl border px-3 py-2" style="border-color: var(--border)">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">Preview</div>
            <div class="mt-1 text-xs font-bold">{{ $watermarkPreview['line1'] }}</div>
            <div class="text-[11px]" style="color: var(--muted)">{{ $watermarkPreview['line2'] }}</div>
        </div>

        <button class="btn btn-primary btn-sm" type="submit">Simpan Watermark</button>
    </form>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-2">
    <div class="card">
        <div class="border-b px-5 py-4" style="border-color: var(--border)">
            <div class="section-title">Impersonation</div>
            <div class="text-xs" style="color: var(--muted)">Masuk sebagai user lain, lalu kembali ke Developer kapan saja.</div>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <table class="table">
                <thead><tr><th>User</th><th>Role</th><th></th></tr></thead>
                <tbody>
                    @foreach ($users as $target)
                        <tr data-search="{{ strtolower($target->name.' '.$target->roleLabel()) }}">
                            <td>
                                <div class="text-sm font-semibold">{{ $target->name }}</div>
                                <div class="text-[11px]" style="color: var(--muted)">{{ $target->email }}</div>
                            </td>
                            <td>
                                <span class="badge badge-muted">{{ $target->roleLabel() }}</span>
                                @if (! $target->is_active)<span class="badge badge-danger">Nonaktif</span>@endif
                            </td>
                            <td>
                                @if ($target->is_active)
                                    <form method="POST" action="{{ route('dev.impersonate', $target) }}">
                                        @csrf
                                        <button class="btn btn-ghost btn-sm" type="submit">Simulasikan</button>
                                    </form>
                                @else
                                    <span class="text-xs" style="color: var(--muted)">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="border-b px-5 py-4" style="border-color: var(--border)">
            <div class="section-title">Dev sessions</div>
            <div class="text-xs" style="color: var(--muted)">Riwayat impersonation (hanya terlihat oleh Developer).</div>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <table class="table">
                <thead><tr><th>Developer</th><th>Target</th><th>Mulai</th><th>Selesai</th></tr></thead>
                <tbody>
                    @forelse ($devSessions as $session)
                        <tr>
                            <td class="text-xs">{{ $session->dev?->name ?: '—' }}</td>
                            <td class="text-xs">{{ $session->target?->name ?: '—' }}</td>
                            <td class="text-xs">{{ $session->started_at?->format('d/m/Y H:i:s') }}</td>
                            <td class="text-xs">{{ $session->ended_at?->format('d/m/Y H:i:s') ?: 'aktif' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-sm" style="color: var(--muted)">Belum ada sesi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-2">
    <div class="card card-pad">
        <div class="section-title">Hapus record</div>
        <p class="mt-1 text-xs" style="color: var(--muted)">Hard delete untuk pengujian. Gunakan dengan hati-hati.</p>
        <form method="POST" action="#" class="mt-3 grid gap-3 sm:grid-cols-3" onsubmit="this.action = '/dev/records/' + this.type.value + '/' + this.record_id.value; this.querySelector('[name=_method]').disabled = false;">
            @csrf
            @method('DELETE')
            <div>
                <label class="label">Tipe</label>
                <select name="type" class="input" required>
                    @foreach (['event' => 'Event', 'task' => 'Task', 'expense' => 'Expense', 'invoice' => 'Invoice', 'report' => 'Report', 'media' => 'Media', 'dealer' => 'Dealer', 'user' => 'User'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">ID</label>
                <input name="record_id" type="number" min="1" class="input" required>
            </div>
            <div>
                <label class="label">Konfirmasi</label>
                <input name="confirmation" class="input" placeholder="DELETE" required>
            </div>
            <div class="sm:col-span-3">
                <button class="btn btn-secondary" type="submit" onclick="return confirm('Hapus record ini secara permanen?')">Hapus Record</button>
            </div>
        </form>
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

<div class="mt-4 grid gap-4 xl:grid-cols-2">
    <div class="card">
        <div class="border-b px-5 py-4" style="border-color: var(--border)">
            <div class="section-title">Failed jobs</div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Queue</th><th>Failed at</th></tr></thead>
                <tbody>
                    @forelse ($failedJobs as $job)
                        <tr>
                            <td class="text-xs">{{ $job->queue }}</td>
                            <td class="text-xs">{{ $job->failed_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-sm" style="color: var(--muted)">Tidak ada failed job.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="border-b px-5 py-4" style="border-color: var(--border)">
            <div class="section-title">Log terakhir</div>
        </div>
        <pre class="max-h-80 overflow-auto p-4 text-[11px]">{{ $logTail }}</pre>
    </div>
</div>

<div class="card mt-4">
    <div class="border-b px-5 py-4" style="border-color: var(--border)">
        <div class="section-title">Routes</div>
        <div class="text-xs" style="color: var(--muted)">{{ $routes->count() }} route terdaftar</div>
    </div>
    <div class="max-h-96 overflow-auto">
        <table class="table">
            <thead><tr><th>Method</th><th>URI</th><th>Name</th><th>Middleware</th></tr></thead>
            <tbody>
                @foreach ($routes as $route)
                    <tr>
                        <td class="whitespace-nowrap text-[11px] font-mono">{{ $route['method'] }}</td>
                        <td class="text-[11px] font-mono">{{ $route['uri'] }}</td>
                        <td class="text-[11px]">{{ $route['name'] }}</td>
                        <td class="max-w-md truncate text-[10px]" style="color: var(--muted)" title="{{ $route['middleware'] }}">{{ $route['middleware'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
