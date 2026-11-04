@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-extrabold tracking-tight">Settings</h1>
    <p class="mt-1 text-sm" style="color: var(--muted)">Profil, keamanan akun, dan preferensi aplikasi.</p>
</div>

<div class="grid gap-6 xl:grid-cols-3">
    <div class="card card-pad xl:col-span-2">
        <form method="POST" action="{{ route('global.settings.update') }}" class="space-y-4">
            @csrf @method('PATCH')
            <div class="section-title">Profil</div>
            <div>
                <label class="label">Nama</label>
                <input name="name" value="{{ old('name', $user->name) }}" class="input" required>
            </div>
            <div>
                <label class="label">Email</label>
                <input value="{{ $user->email }}" class="input" disabled>
            </div>
            <div>
                <label class="label">No. HP</label>
                <input name="phone" value="{{ old('phone', $user->phone) }}" class="input">
            </div>
            <div class="border-t pt-4" style="border-color: var(--border)">
                <div class="section-title mb-3">Keamanan</div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">Password Baru</label>
                        <input type="password" name="password" class="input" placeholder="Kosongkan jika tidak diubah" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="label">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" class="input" autocomplete="new-password">
                    </div>
                </div>
            </div>
            <div class="border-t pt-4" style="border-color: var(--border)">
                <div class="section-title mb-3">Preferensi</div>
                @php $prefs = $user->notification_preferences ?? []; @endphp
                <div class="mt-4">
                    <div class="label">Notifikasi dalam aplikasi</div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (['notify_tasks' => 'Update task & revisi', 'notify_expenses' => 'Expense & pembayaran', 'notify_reports' => 'Report dipublikasikan', 'notify_approvals' => 'Approval & persetujuan'] as $key => $label)
                            <label class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm" style="border-color: var(--border)">
                                <input type="checkbox" name="prefs[{{ $key }}]" value="1" @checked($prefs[$key] ?? true)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Simpan Profil</button>
        </form>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <div class="section-title">Akun</div>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt style="color: var(--muted)">Role</dt><dd class="font-semibold">{{ $user->roleLabel() }}</dd></div>
                <div class="flex justify-between gap-3"><dt style="color: var(--muted)">Dealer</dt><dd class="font-semibold">{{ $user->dealer?->name ?: 'Internal' }}</dd></div>
                <div class="flex justify-between gap-3"><dt style="color: var(--muted)">Status</dt><dd><x-status :status="$user->is_active ? 'active' : 'inactive'" /></dd></div>
                <div class="flex justify-between gap-3"><dt style="color: var(--muted)">Bergabung</dt><dd class="font-semibold">{{ $user->created_at?->format('d/m/Y') }}</dd></div>
            </dl>
        </div>

        <div class="card card-pad">
            <div class="section-title">Aplikasi</div>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt style="color: var(--muted)">Zona waktu</dt><dd class="font-semibold">{{ config('app.timezone') }}</dd></div>
                <div class="flex justify-between gap-3"><dt style="color: var(--muted)">Mata uang</dt><dd class="font-semibold">IDR</dd></div>
                <div class="flex justify-between gap-3"><dt style="color: var(--muted)">Bahasa</dt><dd class="font-semibold">Indonesia</dd></div>
                <div class="flex justify-between gap-3"><dt style="color: var(--muted)">Versi</dt><dd class="font-semibold">{{ config('app.version', '1.0.0') }}</dd></div>
            </dl>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="btn btn-secondary btn-sm" data-pwa-install hidden>Install Aplikasi</button>
                <a href="{{ route('offline.index') }}" class="btn btn-secondary btn-sm">Offline Queue</a>
                <a href="{{ route('conflicts.index') }}" class="btn btn-secondary btn-sm">Konflik Data</a>
                <a href="{{ route('notifications.index') }}" class="btn btn-secondary btn-sm">Notifikasi</a>
                @if ($user->isAdmin())
                    <a href="{{ route('activity-logs.index') }}" class="btn btn-secondary btn-sm">Activity Log</a>
                @endif
            </div>
            <div class="mt-3 text-[11px]" style="color: var(--muted)">PWA mendukung antrean offline untuk foto, expense, dan form visitor.</div>
        </div>
    </div>
</div>
@endsection
