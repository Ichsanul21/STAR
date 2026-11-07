@php($sqlResult = session('sql_result'))

<div class="mb-3">
    <h2 class="text-xl font-extrabold">SQL Runner</h2>
    <p class="text-sm" style="color: var(--muted)">Query read-only untuk investigasi data. Hanya SELECT, SHOW, DESCRIBE, dan EXPLAIN.</p>
</div>

<div class="grid gap-3 sm:grid-cols-3">
    <div class="card card-pad"><div class="mini-label">Driver</div><div class="mt-1 font-bold">{{ $databaseInfo['driver'] }}</div></div>
    <div class="card card-pad"><div class="mini-label">Database</div><div class="mt-1 truncate font-bold">{{ $databaseInfo['database'] }}</div></div>
    <div class="card card-pad"><div class="mini-label">Versi</div><div class="mt-1 font-bold">{{ $databaseInfo['version'] }}</div></div>
</div>

<div class="card card-pad mt-4">
    <form method="POST" action="{{ route('dev.sql') }}" class="space-y-3">
        @csrf
        <div>
            <label class="label" for="devSql">Query</label>
            <textarea id="devSql" name="sql" rows="6" class="input font-mono text-xs" required placeholder="SELECT * FROM events LIMIT 10;">{{ old('sql', $sqlResult['sql'] ?? '') }}</textarea>
        </div>
        @error('sql')
            <div class="rounded-lg px-3 py-2 text-xs" style="background: #fef2f2; color: var(--danger)">{{ $message }}</div>
        @enderror
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-[11px]" style="color: var(--muted)">Maksimal 200 baris ditampilkan. Satu query per eksekusi.</div>
            <button class="btn btn-primary btn-sm" type="submit">Jalankan Query</button>
        </div>
    </form>
</div>

@if ($sqlResult)
    <div class="card mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4" style="border-color: var(--border)">
            <div>
                <div class="section-title">Hasil</div>
                <div class="text-xs" style="color: var(--muted)">{{ $sqlResult['total'] }} baris · {{ $sqlResult['duration'] }} ms</div>
            </div>
            @if ($sqlResult['truncated'])
                <span class="badge badge-warning">Ditampilkan 200 baris pertama</span>
            @endif
        </div>

        @if (empty($sqlResult['rows']))
            <div class="p-5 text-sm" style="color: var(--muted)">Query tidak mengembalikan baris.</div>
        @else
            <div class="max-h-[520px] overflow-auto">
                <table class="table">
                    <thead>
                        <tr>
                            @foreach ($sqlResult['columns'] as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sqlResult['rows'] as $row)
                            <tr>
                                @foreach ($sqlResult['columns'] as $column)
                                    @php($value = $row[$column] ?? null)
                                    <td class="max-w-xs truncate text-[11px] font-mono" title="{{ is_scalar($value) || $value === null ? $value : json_encode($value) }}">
                                        {{ is_scalar($value) || $value === null ? $value : json_encode($value) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
