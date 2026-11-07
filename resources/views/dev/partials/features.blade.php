<div class="mb-3">
    <h2 class="text-xl font-extrabold">Fitur</h2>
    <p class="text-sm" style="color: var(--muted)">Status fitur developer dan pintasan konfigurasi.</p>
</div>

<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    @foreach (['dev_tools' => 'Dev Tools', 'impersonation' => 'Impersonation', 'dev_reset' => 'Reset Database'] as $key => $label)
        @php $active = ($environment[$key] ?? 'disabled') === 'enabled'; @endphp
        <div class="card card-pad">
            <div class="flex items-center justify-between gap-2">
                <div class="text-sm font-bold">{{ $label }}</div>
                <span class="badge {{ $active ? 'badge-success' : 'badge-muted' }}">{{ $active ? 'Aktif' : 'Nonaktif' }}</span>
            </div>
            <div class="mt-2 text-[11px]" style="color: var(--muted)">
                {{ $active ? 'Fitur tersedia untuk akun Developer.' : 'Dinonaktifkan oleh environment variable.' }}
            </div>
        </div>
    @endforeach
</div>

<div class="card card-pad mt-4">
    <div class="section-title">Pintasan</div>
    <div class="mt-3 flex flex-wrap gap-2">
        <a href="{{ route('dev.index', ['section' => 'watermark']) }}" class="btn btn-secondary btn-sm">Watermark</a>
        <a href="{{ route('dev.index', ['section' => 'numbering']) }}" class="btn btn-secondary btn-sm">Penomoran</a>
        <a href="{{ route('dev.index', ['section' => 'sql']) }}" class="btn btn-secondary btn-sm">SQL Runner</a>
        <a href="{{ route('global.settings') }}" class="btn btn-secondary btn-sm">Settings</a>
    </div>
</div>
