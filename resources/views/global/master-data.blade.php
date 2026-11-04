@extends('layouts.app')

@section('title', 'Master Data')

@section('content')
@php $isSuper = auth()->user()->isSuperAdmin(); @endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Master Data</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Dealer dan user STAR.</p>
    </div>
    @if ($isSuper)
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-secondary" data-modal-target="createDealerModal">+ Dealer</button>
            <button type="button" class="btn btn-primary" data-modal-target="createUserModal">+ User</button>
        </div>
    @endif
</div>

<div class="grid gap-6 xl:grid-cols-2">
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b px-5 py-4" style="border-color: var(--border)">
            <div class="section-title">Dealer</div>
            <div class="text-xs" style="color: var(--muted)">{{ $dealers->count() }} dealer</div>
        </div>
        <div class="border-b p-3" style="border-color: var(--border)"><input data-table-filter="dealersTable" class="input max-w-xs" placeholder="Cari dealer…"></div>
        <div class="overflow-x-auto">
            <table class="table" id="dealersTable">
                <thead><tr><th>Kode</th><th>Nama</th><th>Kota</th><th>Event</th><th>Status</th>@if ($isSuper)<th></th>@endif</tr></thead>
                <tbody>
                    @forelse ($dealers as $dealer)
                        <tr data-search="{{ strtolower($dealer->code.' '.$dealer->name.' '.$dealer->city.' '.$dealer->pic_name) }}">
                            <td class="font-semibold">{{ $dealer->code }}</td>
                            <td>
                                <div>{{ $dealer->name }}</div>
                                <div class="text-xs" style="color: var(--muted)">{{ $dealer->pic_name ?: 'PIC belum diisi' }}</div>
                            </td>
                            <td>{{ $dealer->city ?: '—' }}</td>
                            <td>{{ $dealer->events_count }}</td>
                            <td><x-status :status="$dealer->is_active ? 'active' : 'inactive'" /></td>
                            @if ($isSuper)
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <button type="button" class="btn btn-ghost btn-sm" data-modal-target="editDealer{{ $dealer->id }}">Edit</button>
                                        <form method="POST" action="{{ route('global.dealers.toggle', $dealer) }}">
                                            @csrf
                                            <button class="btn btn-ghost btn-sm" type="submit">{{ $dealer->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isSuper ? 6 : 5 }}" class="text-center text-sm" style="color: var(--muted)">Belum ada dealer.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b px-5 py-4" style="border-color: var(--border)">
            <div class="section-title">User</div>
            <div class="text-xs" style="color: var(--muted)">{{ $users->count() }} user</div>
        </div>
        <div class="border-b p-3" style="border-color: var(--border)"><input data-table-filter="usersTable" class="input max-w-xs" placeholder="Cari user…"></div>
        <div class="overflow-x-auto">
            <table class="table" id="usersTable">
                <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Dealer</th><th>Status</th>@if ($isSuper)<th></th>@endif</tr></thead>
                <tbody>
                    @forelse ($users as $row)
                        <tr data-search="{{ strtolower($row->name.' '.$row->email.' '.$row->roleLabel().' '.($row->dealer?->name ?? '')) }}">
                            <td class="font-semibold">{{ $row->name }}</td>
                            <td class="text-xs">{{ $row->email }}</td>
                            <td>{{ $row->roleLabel() }}</td>
                            <td>{{ $row->dealer?->name ?: '—' }}</td>
                            <td><x-status :status="$row->is_active ? 'active' : 'inactive'" /></td>
                            @if ($isSuper)
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <button type="button" class="btn btn-ghost btn-sm" data-modal-target="editUser{{ $row->id }}">Edit</button>
                                        <form method="POST" action="{{ route('global.users.toggle', $row) }}">
                                            @csrf
                                            <button class="btn btn-ghost btn-sm" type="submit" @disabled(auth()->id() === $row->id)>{{ $row->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isSuper ? 6 : 5 }}" class="text-center text-sm" style="color: var(--muted)">Belum ada user.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if (auth()->user()->isAdmin())
    <div class="card mt-6 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4" style="border-color: var(--border)">
            <div>
                <div class="section-title">Referensi · Model Kendaraan</div>
                <div class="text-xs" style="color: var(--muted)">Dipakai di form visitor dan laporan. EO Admin bisa menambah model baru.</div>
            </div>
            <span class="badge badge-muted">{{ $vehicleModels->count() }} model</span>
        </div>
        <form method="POST" action="{{ route('global.lookups.store') }}" class="flex flex-wrap items-end gap-2 border-b p-4" style="border-color: var(--border)">
            @csrf
            <input type="hidden" name="group" value="vehicle_models">
            <div class="min-w-56 flex-1">
                <label class="label">Tambah model</label>
                <input name="label" class="input" placeholder="Honda EM1 e" required>
            </div>
            <button class="btn btn-primary btn-sm" type="submit">Tambah</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table" id="vehicleModelsTable">
                <thead><tr><th>Model</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($vehicleModels as $lookup)
                        <tr data-search="{{ strtolower($lookup->label.' '.$lookup->value) }}">
                            <td>
                                <form method="POST" action="{{ route('global.lookups.update', $lookup) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <input name="label" value="{{ $lookup->label }}" class="input !py-1.5 text-xs" required>
                                    <button class="btn btn-ghost btn-sm" type="submit">Simpan</button>
                                </form>
                            </td>
                            <td><x-status :status="$lookup->is_active ? 'active' : 'inactive'" /></td>
                            <td>
                                <form method="POST" action="{{ route('global.lookups.toggle', $lookup) }}">
                                    @csrf
                                    <button class="btn btn-ghost btn-sm" type="submit">{{ $lookup->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-sm" style="color: var(--muted)">Belum ada model.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($isSuper)
    <x-modal id="createDealerModal" title="Tambah Dealer" size="lg">
        <form method="POST" action="{{ route('global.dealers.store') }}" class="grid gap-3 md:grid-cols-2">
            @csrf
            <div><label class="label">Kode</label><input name="code" value="{{ old('code') }}" class="input" required placeholder="BDG-01"></div>
            <div><label class="label">Nama</label><input name="name" value="{{ old('name') }}" class="input" required placeholder="Honda Bandung"></div>
            <div><label class="label">Kota</label><input name="city" value="{{ old('city') }}" class="input" data-autocomplete data-remote="{{ route('lookup.cities') }}" data-creatable></div>
            <div><label class="label">Telepon</label><input name="phone" value="{{ old('phone') }}" class="input"></div>
            <div class="md:col-span-2"><label class="label">Alamat</label><input name="address" value="{{ old('address') }}" class="input"></div>
            <div class="md:col-span-2"><label class="label">PIC</label><input name="pic_name" value="{{ old('pic_name') }}" class="input"></div>
            <div class="flex justify-end gap-2 md:col-span-2">
                <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                <button class="btn btn-primary" type="submit">Simpan Dealer</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="createUserModal" title="Tambah User" size="lg">
        <form method="POST" action="{{ route('global.users.store') }}" class="grid gap-3 md:grid-cols-2">
            @csrf
            <div><label class="label">Nama</label><input name="name" value="{{ old('name') }}" class="input" required></div>
            <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email') }}" class="input" required></div>
            <div><label class="label">Password</label><input type="password" name="password" class="input" required minlength="8"></div>
            <div>
                <label class="label">Role</label>
                <select name="role" class="input" required>
                    @foreach ((auth()->user()->isDev() ? ['dev' => 'Developer'] : []) + ['super_admin' => 'Super Admin', 'eo_admin' => 'EO Admin', 'finance' => 'Finance', 'pm_event' => 'PM Event', 'division_pic' => 'PIC Divisi', 'field_crew' => 'Field Crew', 'reviewer' => 'Reviewer', 'dealer_admin' => 'Dealer Admin', 'dealer_viewer' => 'Dealer Viewer'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Dealer (untuk role dealer)</label>
                <select name="dealer_id" class="input">
                    <option value="">—</option>
                    @foreach ($dealers as $dealer)
                        <option value="{{ $dealer->id }}" @selected(old('dealer_id') == $dealer->id)>{{ $dealer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label">Telepon</label><input name="phone" value="{{ old('phone') }}" class="input"></div>
            <div class="flex justify-end gap-2 md:col-span-2">
                <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                <button class="btn btn-primary" type="submit">Simpan User</button>
            </div>
        </form>
    </x-modal>

    @foreach ($dealers as $dealer)
        <x-modal id="editDealer{{ $dealer->id }}" title="Edit Dealer {{ $dealer->name }}" size="lg">
            <form method="POST" action="{{ route('global.dealers.update', $dealer) }}" class="grid gap-3 md:grid-cols-2">
                @csrf @method('PATCH')
                <div><label class="label">Kode</label><input name="code" value="{{ $dealer->code }}" class="input" required></div>
                <div><label class="label">Nama</label><input name="name" value="{{ $dealer->name }}" class="input" required></div>
                <div><label class="label">Kota</label><input name="city" value="{{ $dealer->city }}" class="input" data-autocomplete data-remote="{{ route('lookup.cities') }}" data-creatable></div>
                <div><label class="label">Telepon</label><input name="phone" value="{{ $dealer->phone }}" class="input"></div>
                <div class="md:col-span-2"><label class="label">Alamat</label><input name="address" value="{{ $dealer->address }}" class="input"></div>
                <div class="md:col-span-2"><label class="label">PIC</label><input name="pic_name" value="{{ $dealer->pic_name }}" class="input"></div>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                    <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </x-modal>
    @endforeach

    @foreach ($users as $row)
        <x-modal id="editUser{{ $row->id }}" title="Edit User {{ $row->name }}" size="lg">
            <form method="POST" action="{{ route('global.users.update', $row) }}" class="grid gap-3 md:grid-cols-2">
                @csrf @method('PATCH')
                <div><label class="label">Nama</label><input name="name" value="{{ $row->name }}" class="input" required></div>
                <div><label class="label">Email</label><input type="email" name="email" value="{{ $row->email }}" class="input" required></div>
                <div>
                    <label class="label">Role</label>
                    <select name="role" class="input" required>
                        @foreach ((auth()->user()->isDev() ? ['dev' => 'Developer'] : []) + ['super_admin' => 'Super Admin', 'eo_admin' => 'EO Admin', 'finance' => 'Finance', 'pm_event' => 'PM Event', 'division_pic' => 'PIC Divisi', 'field_crew' => 'Field Crew', 'reviewer' => 'Reviewer', 'dealer_admin' => 'Dealer Admin', 'dealer_viewer' => 'Dealer Viewer'] as $value => $label)
                            <option value="{{ $value }}" @selected($row->role === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Dealer (untuk role dealer)</label>
                    <select name="dealer_id" class="input">
                        <option value="">—</option>
                        @foreach ($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected($row->dealer_id == $dealer->id)>{{ $dealer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Telepon</label><input name="phone" value="{{ $row->phone }}" class="input"></div>
                <div><label class="label">Password Baru</label><input type="password" name="password" class="input" placeholder="Kosongkan jika tidak diubah" autocomplete="new-password"></div>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                    <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </x-modal>
    @endforeach
@endif
@endsection
