<section id="reports" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Reports</h2>
            <p class="text-sm" style="color: var(--muted)">Versi laporan, PDF, Excel, publish, dan acknowledge dealer.</p>
        </div>
        @if (auth()->user()->canManageEvent($event))
            <form method="POST" action="{{ route('reports.generate', $event) }}">
                @csrf
                <button class="btn btn-primary" type="submit">Generate versi baru</button>
            </form>
        @endif
    </div>

    <div class="space-y-3">
        @forelse ($event->reports as $report)
            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold">Laporan {{ $report->report_number ?: 'v'.$report->version }}</span>
                            @if ($report->report_number)
                                <span class="badge badge-muted">v{{ $report->version }}</span>
                            @endif
                            <x-status :status="$report->status" />
                        </div>
                        <div class="mt-1 text-xs" style="color: var(--muted)">
                            Dibuat {{ $report->created_at->format('d/m/Y H:i') }}
                            @if ($report->generator) · oleh {{ $report->generator->name }} @endif
                            @if ($report->published_at) · published {{ $report->published_at->format('d/m/Y H:i') }} @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('reports.preview', $report) }}" class="btn btn-secondary btn-sm" data-preview="html" data-preview-title="Preview Laporan {{ $report->report_number ?: 'v'.$report->version }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::report($report)) }}">Preview</a>
                        @if ($report->pdf_path)
                            <a href="{{ route('reports.pdf', $report) }}" class="btn btn-secondary btn-sm" data-preview="pdf" data-preview-title="PDF Laporan {{ $report->report_number ?: 'v'.$report->version }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::report($report)) }}">PDF</a>
                        @endif
                        @if ($report->xlsx_path)
                            <a href="{{ route('reports.xlsx', $report) }}" class="btn btn-secondary btn-sm">Excel</a>
                        @endif
                        @if (auth()->user()->canManageEvent($event) && $report->status !== 'published')
                            <form method="POST" action="{{ route('reports.publish', $report) }}">@csrf<button class="btn btn-primary btn-sm">Publish</button></form>
                        @endif
                        @if (auth()->user()->isDealer() && $report->status === 'published' && ! $report->acknowledged_at)
                            <form method="POST" action="{{ route('reports.acknowledge', $report) }}">@csrf<button class="btn btn-primary btn-sm">Acknowledge</button></form>
                        @endif
                        @if (auth()->user()->isDev() && $report->status === 'published')
                            <form method="POST" action="{{ route('dev.reports.unpublish', $report) }}">@csrf<button class="btn btn-ghost btn-sm" onclick="return confirm('Buka publikasi laporan ini?')">Unpublish (Dev)</button></form>
                        @endif
                    </div>
                </div>

                @if ($report->snapshot)
                    <div class="mt-3 grid gap-3 text-xs sm:grid-cols-4">
                        <div><span style="color: var(--muted)">Progress</span><div class="font-bold">{{ $report->snapshot['progress'] ?? 0 }}%</div></div>
                        <div><span style="color: var(--muted)">Task</span><div class="font-bold">{{ $report->snapshot['tasks']['done'] ?? 0 }}/{{ $report->snapshot['tasks']['total'] ?? 0 }}</div></div>
                        <div><span style="color: var(--muted)">Foto</span><div class="font-bold">{{ $report->snapshot['media'] ?? 0 }}</div></div>
                        <div><span style="color: var(--muted)">Pengunjung</span><div class="font-bold">{{ $report->snapshot['visitors'] ?? 0 }}</div></div>
                    </div>
                @endif

                @if ((auth()->user()->canManageEvent($event) || auth()->user()->isDev()) && ($report->status !== 'published' || auth()->user()->isDev()))
                    @php
                        $sectionLabels = \App\Services\ReportService::SECTIONS;
                        $sectionOrder = $report->section_order ?: array_keys($sectionLabels);
                    @endphp
                    <details class="mt-3 rounded-xl border p-3" style="border-color: var(--border)">
                        <summary class="cursor-pointer text-xs font-bold" style="color: var(--brand)">Atur urutan section</summary>
                        <form method="POST" action="{{ route('reports.sections', $report) }}" class="mt-3" data-section-form>
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $report->lock_version }}">
                            <ul class="space-y-1" data-sortable>
                                @foreach ($sectionOrder as $sectionKey)
                                    @if (isset($sectionLabels[$sectionKey]))
                                        <li draggable="true" data-section-key="{{ $sectionKey }}" class="flex cursor-grab items-center gap-2 rounded-lg border bg-white px-3 py-2 text-xs" style="border-color: var(--border)">
                                            <span style="color: var(--muted)">☰</span>
                                            <span class="font-medium">{{ $sectionLabels[$sectionKey] }}</span>
                                            <input type="hidden" name="sections[]" value="{{ $sectionKey }}">
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                            <div class="mt-3">
                                <button class="btn btn-secondary btn-sm" type="submit">Simpan Urutan</button>
                                <span class="ml-2 text-xs" style="color: var(--muted)">Drag untuk mengubah urutan PDF/preview.</span>
                            </div>
                        </form>
                    </details>

                    <details class="mt-3 rounded-xl border p-3" style="border-color: var(--border)">
                        <summary class="cursor-pointer text-xs font-bold" style="color: var(--brand)">Edit narasi laporan</summary>
                        <form method="POST" action="{{ route('reports.narrative', $report) }}" class="mt-3 grid gap-3 lg:grid-cols-2">
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $report->lock_version }}">
                            @foreach ([
                                'summary' => 'Ringkasan Eksekutif',
                                'evaluation' => 'Evaluasi & Lessons Learned',
                                'recommendations' => 'Rekomendasi & Action Plan',
                                'conclusion' => 'Kesimpulan',
                            ] as $field => $label)
                                <div class="{{ $field === 'summary' ? 'lg:col-span-2' : '' }}">
                                    <x-rich-text :name="$field" :label="$label" :value="$report->narrative[$field] ?? ''" />
                                </div>
                            @endforeach
                            <div class="lg:col-span-2">
                                <button class="btn btn-primary btn-sm" type="submit">Simpan Narasi</button>
                                <span class="ml-2 text-xs" style="color: var(--muted)">Generate versi baru setelah narasi disimpan untuk memperbarui PDF/Excel.</span>
                            </div>
                        </form>
                    </details>
                @endif
            </div>
        @empty
            <x-empty title="Belum ada laporan" body="Generate laporan setelah event selesai. Laporan akan berisi progress, RAB, dokumentasi, dan hasil event." />
        @endforelse
    </div>
</section>

@push('scripts')
<script>
(() => {
    document.querySelectorAll('[data-sortable]').forEach((list) => {
        let dragged = null;

        list.querySelectorAll('[data-section-key]').forEach((item) => {
            item.addEventListener('dragstart', () => {
                dragged = item;
                item.style.opacity = '0.45';
            });
            item.addEventListener('dragend', () => {
                item.style.opacity = '1';
                dragged = null;
            });
        });

        list.addEventListener('dragover', (event) => {
            event.preventDefault();
            const target = event.target.closest('[data-section-key]');
            if (!dragged || !target || target === dragged) return;
            const box = target.getBoundingClientRect();
            const after = event.clientY > box.top + box.height / 2;
            list.insertBefore(dragged, after ? target.nextSibling : target);
        });
    });
})();
</script>
@endpush
