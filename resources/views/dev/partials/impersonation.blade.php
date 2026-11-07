<div class="mb-3">
    <h2 class="text-xl font-extrabold">Masuk Sebagai</h2>
    <p class="text-sm" style="color: var(--muted)">Impersonation user dan riwayat sesi developer.</p>
</div>

<div class="grid gap-4 xl:grid-cols-2">
    <div class="card">
        <div class="border-b px-5 py-4" style="border-color: var(--border)">
            <div class="section-title">Pilih user</div>
            <div class="text-xs" style="color: var(--muted)">Masuk sebagai user lain, lalu kembali ke Developer kapan saja.</div>
        </div>
        <div class="max-h-[420px] overflow-y-auto">
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
        <div class="max-h-[420px] overflow-y-auto">
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
