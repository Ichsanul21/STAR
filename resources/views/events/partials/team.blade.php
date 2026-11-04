<section id="team" class="scroll-mt-24">
    <div class="mb-3">
        <h2 class="text-xl font-extrabold">Team & Struktur Event</h2>
        <p class="text-sm" style="color: var(--muted)">Fase, divisi, dan anggota event. Fase menentukan rollup progress.</p>
    </div>

    @if (auth()->user()->canManageEvent($event))
    <details class="card mb-4">
        <summary class="cursor-pointer px-5 py-4 text-sm font-bold" style="color: var(--brand)">+ Kelola Fase / Divisi / Anggota</summary>
        <div class="grid gap-4 border-t p-4 lg:grid-cols-3" style="border-color: var(--border)">
            <div class="card card-pad">
                <div class="section-title">Tambah Fase</div>
                <form method="POST" action="{{ route('events.phases.store', $event) }}" class="mt-3 space-y-3">
                    @csrf
                    <div><label class="label">Nama</label><input name="name" class="input" required placeholder="Survey & Persiapan"></div>
                    <div><label class="label">Bobot</label><input type="number" step="0.1" name="weight" value="1" class="input" required></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="label">Mulai</label><input type="date" name="start_date" class="input"></div>
                        <div><label class="label">Selesai</label><input type="date" name="end_date" class="input"></div>
                    </div>
                    <button class="btn btn-secondary btn-sm" type="submit">Tambah Fase</button>
                </form>
            </div>

            <div class="card card-pad">
                <div class="section-title">Tambah Divisi</div>
                <form method="POST" action="{{ route('events.divisions.store', $event) }}" class="mt-3 space-y-3">
                    @csrf
                    <div><label class="label">Nama Divisi</label><input name="name" class="input" required placeholder="Sales"></div>
                    <button class="btn btn-secondary btn-sm" type="submit">Tambah Divisi</button>
                </form>
            </div>

            <div class="card card-pad">
                <div class="section-title">Tambah Anggota</div>
                <form method="POST" action="{{ route('events.members.store', $event) }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <label class="label">User</label>
                        <select name="user_id" class="input" required>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->roleLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Role Event</label>
                        <select name="role" class="input">
                            <option value="pm_event">PM Event</option>
                            <option value="division_pic">Division PIC</option>
                            <option value="field_crew">Field Crew</option>
                            <option value="reviewer">Reviewer</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Divisi</label>
                        <select name="division_id" class="input">
                            <option value="">Tanpa divisi</option>
                            @foreach ($event->divisions as $division)
                                <option value="{{ $division->id }}">{{ $division->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-secondary btn-sm" type="submit">Tambah Anggota</button>
                </form>
            </div>
        </div>
    </details>
    @endif

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card">
            <div class="border-b px-5 py-4" style="border-color: var(--border)"><div class="section-title">Fase</div></div>
            <div class="divide-y" style="border-color: var(--border)">
                @foreach ($event->phases as $phase)
                    <div class="flex items-center justify-between gap-2 px-5 py-3 text-sm">
                        <div><div class="font-semibold">{{ $phase->name }}</div><div class="text-xs" style="color: var(--muted)">Bobot {{ (float) $phase->weight }}</div></div>
                        <x-status :status="$phase->status" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="border-b px-5 py-4" style="border-color: var(--border)"><div class="section-title">Divisi</div></div>
            <div class="divide-y" style="border-color: var(--border)">
                @foreach ($event->divisions as $division)
                    <div class="px-5 py-3 text-sm font-semibold">{{ $division->name }}</div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="border-b px-5 py-4" style="border-color: var(--border)"><div class="section-title">Anggota</div></div>
            <div class="divide-y" style="border-color: var(--border)">
                @forelse ($event->members as $member)
                    <div class="px-5 py-3 text-sm">
                        <div class="font-semibold">{{ $member->user?->name }}</div>
                        <div class="text-xs" style="color: var(--muted)">{{ $member->role }} · {{ $member->division?->name ?: 'Tanpa divisi' }}</div>
                    </div>
                @empty
                    <div class="px-5 py-4 text-sm" style="color: var(--muted)">Belum ada anggota.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if (auth()->user()->canManageEvent($event))
        <div class="card card-pad mt-4">
            <div class="section-title">Pengaturan Event</div>
            <form method="POST" action="{{ route('events.update', $event) }}" class="mt-3 grid gap-3 md:grid-cols-3">
                @csrf @method('PATCH')
                <input type="hidden" name="lock_version" value="{{ $event->lock_version }}">
                <x-merge-base :model="$event" :fields="['name', 'type', 'location', 'city', 'start_date', 'end_date', 'capture_mode', 'target_visitors', 'target_leads', 'target_units', 'contract_value', 'status', 'description']" />
                <div><label class="label">Nama</label><input name="name" value="{{ $event->name }}" class="input" required></div>
                <div><label class="label">Status</label><select name="status" class="input">@foreach (['draft' => 'Draft', 'active' => 'Aktif', 'closed' => 'Selesai'] as $value => $label)<option value="{{ $value }}" @selected($event->status === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="label">Capture Mode</label><select name="capture_mode" class="input"><option value="camera_only" @selected($event->capture_mode === 'camera_only')>Camera only</option><option value="upload_or_camera" @selected($event->capture_mode === 'upload_or_camera')>Upload / Camera</option></select></div>
                <div><label class="label">Lokasi</label><input name="location" value="{{ $event->location }}" class="input" data-autocomplete data-remote="{{ route('lookup.locations') }}" data-creatable></div>
                <div><label class="label">Kota</label><input name="city" value="{{ $event->city }}" class="input" data-autocomplete data-remote="{{ route('lookup.cities') }}" data-creatable></div>
                <div><label class="label">Target Pengunjung</label><input type="number" name="target_visitors" value="{{ $event->target_visitors }}" class="input"></div>
                <div><label class="label">Target Lead</label><input type="number" name="target_leads" value="{{ $event->target_leads }}" class="input"></div>
                <div><label class="label">Target Unit</label><input type="number" name="target_units" value="{{ $event->target_units }}" class="input"></div>
                <div class="md:col-span-3"><label class="label">Deskripsi</label><textarea name="description" class="input" rows="2">{{ $event->description }}</textarea></div>
                <div class="md:col-span-3"><button class="btn btn-primary btn-sm" type="submit">Simpan Pengaturan</button></div>
            </form>
        </div>
    @endif
</section>
