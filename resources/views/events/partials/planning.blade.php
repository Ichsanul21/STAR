<section id="planning" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Planning & Rundown</h2>
            <p class="text-sm" style="color: var(--muted)">Import XLSX untuk rencana kegiatan dan rundown event.</p>
        </div>
        <div class="text-xs" style="color: var(--muted)">{{ $event->planningItems->count() }} planning · {{ $event->scheduleItems->count() }} rundown</div>
    </div>

    @if (auth()->user()->canManageEvent($event))
    <details class="card mb-4">
        <summary class="cursor-pointer px-5 py-4 text-sm font-bold" style="color: var(--brand)">+ Import Planning / Rundown</summary>
        <div class="grid gap-4 border-t p-4 lg:grid-cols-2" style="border-color: var(--border)">
            <div class="card card-pad">
                <div class="section-title">Import Planning XLSX</div>
                <p class="mt-1 text-xs" style="color: var(--muted)">Header: aspek, deskripsi, target, pic, deadline, status, catatan.</p>
                <form method="POST" action="{{ route('imports.preview', $event) }}" enctype="multipart/form-data" class="mt-3 flex gap-2">
                    @csrf
                    <input type="hidden" name="type" value="planning">
                    <input type="file" name="file" class="input !py-1.5 text-xs" accept=".xlsx" required>
                    <button class="btn btn-secondary btn-sm" type="submit">Preview & Mapping</button>
                </form>
            </div>

            <div class="card card-pad">
                <div class="section-title">Import Rundown XLSX</div>
                <p class="mt-1 text-xs" style="color: var(--muted)">Header: tanggal, mulai, selesai, aktivitas, pic, lokasi, kategori, target_peserta, realisasi_peserta, status, catatan.</p>
                <form method="POST" action="{{ route('imports.preview', $event) }}" enctype="multipart/form-data" class="mt-3 flex gap-2">
                    @csrf
                    <input type="hidden" name="type" value="rundown">
                    <input type="file" name="file" class="input !py-1.5 text-xs" accept=".xlsx" required>
                    <button class="btn btn-secondary btn-sm" type="submit">Preview & Mapping</button>
                </form>
            </div>
        </div>
    </details>
    @endif

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b px-5 py-4" style="border-color: var(--border)"><div class="section-title">Planning</div></div>
            <div class="border-b p-3" style="border-color: var(--border)">
                <input data-table-filter="planningTable" class="input max-w-xs" placeholder="Cari aspek / PIC…">
            </div>
            <div class="overflow-x-auto">
                <table class="table" id="planningTable">
                    <thead><tr><th>Aspek</th><th>Deskripsi</th><th>Target</th><th>PIC</th><th>Deadline</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($event->planningItems as $item)
                            <tr data-search="{{ strtolower($item->aspect.' '.($item->description ?? '').' '.($item->pic ?? '')) }}">
                                <td class="font-semibold">{{ $item->aspect }}</td>
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->target ?: '—' }}</td>
                                <td>{{ $item->pic ?: '—' }}</td>
                                <td>{{ $item->deadline?->format('d/m/Y') ?: '—' }}</td>
                                <td><x-status :status="$item->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-sm" style="color: var(--muted)">Belum ada planning. Import XLSX.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b px-5 py-4" style="border-color: var(--border)"><div class="section-title">Rundown</div></div>
            <div class="border-b p-3" style="border-color: var(--border)">
                <input data-table-filter="rundownTable" class="input max-w-xs" placeholder="Cari aktivitas / PIC…">
            </div>
            <div class="overflow-x-auto">
                <table class="table" id="rundownTable">
                    <thead><tr><th>Tanggal</th><th>Waktu</th><th>Aktivitas</th><th>PIC</th><th>Lokasi</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($event->scheduleItems as $item)
                            <tr data-search="{{ strtolower($item->activity.' '.($item->pic ?? '').' '.($item->location ?? '')) }}">
                                <td class="whitespace-nowrap">{{ $item->date?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap">{{ $item->start_time ?: '—' }}{{ $item->end_time ? ' – '.$item->end_time : '' }}</td>
                                <td class="font-semibold">{{ $item->activity }}</td>
                                <td>{{ $item->pic ?: '—' }}</td>
                                <td>{{ $item->location ?: '—' }}</td>
                                <td><x-status :status="$item->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-sm" style="color: var(--muted)">Belum ada rundown. Import XLSX.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
