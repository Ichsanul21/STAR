@extends('layouts.app')

@section('title', 'Import Mapping')

@section('content')
<div class="mb-6">
    <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--muted)">{{ $batch->event->name }} · {{ strtoupper($batch->type) }}</div>
    <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Mapping Kolom XLSX</h1>
    <p class="mt-1 text-sm" style="color: var(--muted)">Pilih kolom file untuk setiap field. Mapping otomatis sudah dicoba; koreksi jika perlu.</p>
</div>

<form method="POST" action="{{ route('imports.commit', $batch) }}">
    @csrf

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card card-pad xl:col-span-1">
            <div class="section-title">Mapping</div>
            <div class="mt-4 space-y-3">
                @foreach ($targets as $key => $label)
                    <div>
                        <label class="label">{{ $label }}</label>
                        <select name="mapping[{{ $key }}]" class="input">
                            <option value="">— Tidak dipakai —</option>
                            @foreach ($parsed['labels'] as $column => $header)
                                <option value="{{ $column }}" @selected(($suggested[$key] ?? null) === $column)>{{ $header ?: $column }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
            <div class="mt-5 flex gap-2">
                <button class="btn btn-primary" type="submit">Import Sekarang</button>
                <a href="{{ route('events.show', $batch->event) }}" class="btn btn-secondary">Batal</a>
            </div>
        </div>

        <div class="card xl:col-span-2">
            <div class="border-b px-5 py-4" style="border-color: var(--border)">
                <div class="section-title">Preview Data</div>
                <div class="text-xs" style="color: var(--muted)">5 baris pertama</div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            @foreach ($parsed['labels'] as $column => $header)
                                <th>{{ $header ?: $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sample as $row)
                            <tr>
                                @foreach ($parsed['labels'] as $column => $header)
                                    <td>{{ $row[$column] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($parsed['labels']) }}" class="text-center text-sm" style="color: var(--muted)">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>
@endsection
