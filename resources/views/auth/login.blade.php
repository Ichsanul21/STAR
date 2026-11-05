@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="grid min-h-[75vh] items-center gap-10 lg:grid-cols-2">
    <div class="hidden lg:block">
        <div class="text-5xl font-extrabold tracking-tight">STAR</div>
        <div class="mt-2 text-xs font-bold uppercase tracking-[0.24em]" style="color: var(--muted)">Supported by Alenkosa</div>
        <h1 class="mt-10 max-w-xl text-4xl font-extrabold leading-tight">Event operations, finance, and reporting in one place.</h1>
        <p class="mt-4 max-w-lg text-base leading-relaxed" style="color: var(--muted)">Kelola RAB, task, dokumentasi, pembayaran client, dan laporan event dealer tanpa data yang terputus.</p>
        <div class="mt-8 grid max-w-lg grid-cols-3 gap-3">
            <div class="card card-pad"><div class="text-lg font-bold">RAB</div><div class="text-xs" style="color: var(--muted)">Plan → final → realisasi</div></div>
            <div class="card card-pad"><div class="text-lg font-bold">Task</div><div class="text-xs" style="color: var(--muted)">Rollup per fase</div></div>
            <div class="card card-pad"><div class="text-lg font-bold">Report</div><div class="text-xs" style="color: var(--muted)">PDF + Excel</div></div>
        </div>
    </div>

    <div class="mx-auto w-full max-w-md">
        <div class="card card-pad">
            <div class="text-2xl font-extrabold">Masuk ke STAR</div>
            <p class="mt-1 text-sm" style="color: var(--muted)">Gunakan akun yang diberikan admin.</p>

            @if (request('reset'))
                <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-800">
                    {{ request('reset') === 'reseeded' ? 'Database demo berhasil di-reseed.' : 'Database berhasil dihapus. Silakan login dengan akun Developer.' }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required autofocus placeholder="nama@perusahaan.com">
                </div>
                <div>
                    <label class="label" for="password">Password</label>
                    <input id="password" name="password" type="password" class="input" required placeholder="••••••••">
                </div>
                <label class="flex items-center gap-2 text-sm" style="color: var(--muted)">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Ingat saya
                </label>
                <button class="btn btn-primary w-full" type="submit">Masuk</button>
            </form>

            @if (config('app.demo_login'))
                <div class="mt-6 border-t pt-5" style="border-color: var(--border)">
                    <div class="flex items-end justify-between gap-2">
                        <div>
                            <div class="text-sm font-bold">Login Demo</div>
                            <div class="text-xs" style="color: var(--muted)">Klik akun untuk langsung masuk.</div>
                        </div>
                        <span class="badge badge-muted">Demo</span>
                    </div>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ([
                            'admin' => 'Super Admin',
                            'eo' => 'EO Admin',
                            'finance' => 'Finance',
                            'pm' => 'PM Event',
                            'field' => 'Field Crew',
                            'pic' => 'PIC Divisi',
                            'reviewer' => 'Reviewer',
                            'dealer-jkt' => 'Dealer Jakarta',
                            'dealer-bdg' => 'Dealer Bandung',
                            'dealer-sby' => 'Dealer Surabaya',
                            'dealer-viewer' => 'Dealer Viewer',
                        ] as $key => $label)
                            <form method="POST" action="{{ route('demo.login', $key) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm w-full justify-between">
                                    <span>{{ $label }}</span>
                                    <span style="color: var(--muted)">→</span>
                                </button>
                            </form>
                        @endforeach
                    </div>

                    <div class="mt-3 text-[10px]" style="color: var(--muted)">
                        Semua akun demo memakai password <strong>password</strong>. Section ini hanya aktif di environment demo/local.
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
