@extends('layouts.app')

@section('title', 'Detail Konflik')

@section('content')
<div class="mb-4"><a href="{{ route('conflicts.index') }}" class="link text-xs">← Kembali ke Konflik Data</a></div>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Konflik {{ $conflict->modelLabel() }} #{{ $conflict->model_id ?: '—' }}</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">
            {{ $conflict->created_at?->format('d/m/Y H:i:s') }} · {{ $conflict->user?->name ?: 'System' }}
            · {{ $conflict->event?->name ?: 'tanpa event' }}
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($conflict->isResolved())
            <form method="POST" action="{{ route('conflicts.reopen', $conflict) }}">
                @csrf
                <button class="btn btn-secondary" type="submit">Buka Kembali</button>
            </form>
        @else
            <form method="POST" action="{{ route('conflicts.resolve', $conflict) }}">
                @csrf
                <button class="btn btn-primary" type="submit">Tandai Selesai</button>
            </form>
        @endif
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card card-pad">
        <div class="text-xs" style="color: var(--muted)">Versi dikirim</div>
        <div class="mt-1 text-xl font-extrabold">v{{ $conflict->expected_version ?? '—' }}</div>
    </div>
    <div class="card card-pad">
        <div class="text-xs" style="color: var(--muted)">Versi server</div>
        <div class="mt-1 text-xl font-extrabold">v{{ $conflict->actual_version ?? '—' }}</div>
    </div>
    <div class="card card-pad">
        <div class="text-xs" style="color: var(--muted)">Status</div>
        <div class="mt-1">
            @if ($conflict->isResolved())
                <span class="badge badge-success">Selesai</span>
                <div class="mt-1 text-xs" style="color: var(--muted)">oleh {{ $conflict->resolver?->name ?: '—' }} · {{ $conflict->resolved_at?->format('d/m/Y H:i') }}</div>
            @else
                <span class="badge badge-danger">Terbuka</span>
            @endif
        </div>
    </div>
    <div class="card card-pad">
        <div class="text-xs" style="color: var(--muted)">Halaman</div>
        <div class="mt-1 truncate text-xs font-semibold" title="{{ $conflict->url }}">{{ $conflict->route ?: '—' }}</div>
    </div>
</div>

<div class="card mt-6 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4" style="border-color: var(--border)">
        <div class="section-title">Perbandingan & Merge Data</div>
        @if ($plan)
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="badge badge-muted">{{ count($plan) }} field</span>
                <span class="badge badge-success">{{ collect($plan)->where('resolution', '!=', 'manual')->count() }} otomatis</span>
                <span class="badge badge-warning">{{ collect($plan)->where('disputed', true)->count() }} perlu dipilih</span>
            </div>
        @endif
    </div>

    @if ($plan)
        <form method="POST" action="{{ route('conflicts.merge', $conflict) }}">
            @csrf
            <div class="border-b px-5 py-3 text-xs" style="border-color: var(--border); color: var(--muted)">
                Merge tiga arah: perubahan yang hanya kamu buat diambil otomatis, perubahan yang hanya ada di server dipertahankan,
                dan field yang diubah dua-duanya harus kamu pilih. Status task dan field hitungan tidak termasuk.
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Field</th><th>Base</th><th>Dikirim</th><th>Server</th><th>Hasil</th></tr></thead>
                    <tbody>
                        @foreach ($plan as $row)
                            <tr class="{{ $row['disputed'] ? 'row-selected' : '' }}">
                                <td class="text-xs">
                                    <div class="font-semibold">{{ $row['label'] }}</div>
                                    <div class="font-mono text-[10px]" style="color: var(--muted)">{{ $row['field'] }}</div>
                                </td>
                                <td class="max-w-[16rem] break-words text-xs">{{ $row['base'] ?? '—' }}</td>
                                <td class="max-w-[16rem] break-words text-xs">
                                    @if ($row['submitted'] === null)
                                        <span style="color: var(--muted)">—</span>
                                    @else
                                        <span class="{{ $row['user_changed'] ? 'font-semibold' : '' }}">{{ $row['submitted'] }}</span>
                                    @endif
                                </td>
                                <td class="max-w-[16rem] break-words text-xs">
                                    @if ($row['server'] === null)
                                        <span style="color: var(--muted)">—</span>
                                    @else
                                        <span class="{{ $row['server_changed'] ? 'font-semibold' : '' }}">{{ $row['server'] }}</span>
                                    @endif
                                </td>
                                <td class="min-w-40 text-xs">
                                    @if ($row['disputed'])
                                        <div class="flex flex-col gap-1">
                                            <label class="flex items-center gap-1.5">
                                                <input type="radio" name="decisions[{{ $row['field'] }}]" value="server" checked>
                                                <span>Server</span>
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input type="radio" name="decisions[{{ $row['field'] }}]" value="user">
                                                <span>Dikirim</span>
                                            </label>
                                        </div>
                                    @elseif ($row['resolution'] === 'user')
                                        <span class="badge badge-brand">Pakai dikirim</span>
                                    @else
                                        <span class="badge badge-muted">Pakai server</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4" style="border-color: var(--border)">
                <div class="text-xs" style="color: var(--muted)">Merge akan menaikkan versi data dan tercatat di activity log.</div>
                <div class="flex gap-2">
                    <a href="{{ route('conflicts.index') }}" class="btn btn-secondary">Batal</a>
                    <button class="btn btn-primary" type="submit">Terapkan Hasil Merge</button>
                </div>
            </div>
        </form>
    @else
        <div class="border-b px-5 py-3 text-xs" style="border-color: var(--border); color: var(--muted)">
            Konflik ini tidak punya base snapshot (dibuat sebelum fitur merge atau dari form tanpa snapshot).
            Bandingkan nilai dikirim vs server di bawah, lalu edit ulang data dengan versi terbaru.
            Baris bertanda berbeda perlu digabung manual.
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Field</th><th>Dikirim</th><th>Server saat ini</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="{{ $row['differs'] ? 'row-selected' : '' }}">
                            <td class="font-mono text-xs font-semibold">{{ $row['field'] }}</td>
                            <td class="max-w-md break-words text-xs">{{ $row['submitted'] !== '' ? $row['submitted'] : '—' }}</td>
                            <td class="max-w-md break-words text-xs">{{ $row['current'] !== '' ? $row['current'] : '—' }}</td>
                            <td>
                                @if ($row['differs'])
                                    <span class="badge badge-warning">Berbeda</span>
                                @else
                                    <span class="badge badge-muted">Sama</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-sm" style="color: var(--muted)">Tidak ada data perbandingan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

@if (! empty($conflict->snapshot))
    <details class="card mt-4 p-5">
        <summary class="cursor-pointer text-sm font-bold" style="color: var(--brand)">Snapshot server (JSON)</summary>
        <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-50 p-4 text-[11px]">{{ json_encode($conflict->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </details>
@endif

@if (! empty($conflict->attempted))
    <details class="card mt-4 p-5">
        <summary class="cursor-pointer text-sm font-bold" style="color: var(--brand)">Data yang dikirim (JSON)</summary>
        <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-50 p-4 text-[11px]">{{ json_encode($conflict->attempted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </details>
@endif
@endsection
