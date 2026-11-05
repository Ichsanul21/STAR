@php
    $versions = $event->budgetVersions;
    $current = $budget ?: $versions->first();
    $items = $current?->items ?? collect();
    $projectValue = (float) ($event->contract_value ?? $financials['planned'] ?? 0);
    $paidTotal = (float) ($financials['paid'] ?? 0);
    $expenseTotal = (float) ($financials['actual'] ?? 0);
    $eventPnl = $projectValue - $expenseTotal;
@endphp

<section id="budget" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Budget / RAB</h2>
            <p class="text-sm" style="color: var(--muted)">Planning → final → realisasi. Final RAB dikunci dan tidak bisa diedit.</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($versions->isNotEmpty())
                <select class="input !w-auto !py-1.5 text-xs" onchange="window.location='{{ route('events.show', $event) }}?budget_version='+this.value">
                    @foreach ($versions as $version)
                        <option value="{{ $version->id }}" @selected($current?->id === $version->id)>
                            {{ ucfirst($version->stage) }} v{{ $version->version }} · {{ $version->status }}
                        </option>
                    @endforeach
                </select>
            @endif
            @if ($current)
                <x-status :status="$current->stage === 'final' ? 'done' : ($current->status === 'approved' ? 'approved' : 'draft')" />
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Nilai Project (omzet)</div><div class="mt-1 text-xl font-extrabold"><x-money :value="$projectValue" /></div></div>
        <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Sudah Dibayar</div><div class="mt-1 text-xl font-extrabold" style="color: var(--success)"><x-money :value="$paidTotal" /></div></div>
        <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Total Expense</div><div class="mt-1 text-xl font-extrabold"><x-money :value="$expenseTotal" /></div></div>
        <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Total PnL</div><div class="mt-1 text-xl font-extrabold" style="color: {{ $eventPnl >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$eventPnl" /></div></div>
    </div>

    @if (auth()->user()->canFinanceEvent($event))
    <details class="card mt-4">
        <summary class="cursor-pointer px-5 py-4 text-sm font-bold" style="color: var(--brand)">+ Kelola RAB / Import / Finalisasi</summary>
        <div class="grid gap-4 border-t p-4 lg:grid-cols-2" style="border-color: var(--border)">
            <div class="card card-pad">
                <div class="section-title">Buat versi RAB</div>
                <form method="POST" action="{{ route('budget.versions.store', $event) }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="w-40">
                        <label class="label">Stage</label>
                        <select name="stage" class="input">
                            <option value="planning">Planning</option>
                            <option value="final">Final</option>
                        </select>
                    </div>
                    <button class="btn btn-secondary" type="submit">Buat versi</button>
                </form>
                @if ($current && ! $current->isFinal())
                    <form method="POST" action="{{ route('budget.finalize', $current) }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="lock_version" value="{{ $current->lock_version }}">
                        <button class="btn btn-primary" type="submit">Finalisasi & kunci RAB</button>
                    </form>
                    <p class="mt-2 text-xs" style="color: var(--muted)">Finalisasi planning akan membuat Final RAB v1 yang immutable.</p>
                @endif

                <form method="POST" action="{{ route('imports.preview', $event) }}" enctype="multipart/form-data" class="mt-4 rounded-xl border p-3" style="border-color: var(--border)">
                    @csrf
                    <input type="hidden" name="type" value="rab">
                    <div class="text-xs font-bold">Import RAB XLSX</div>
                    <p class="mt-1 text-[11px]" style="color: var(--muted)">Upload XLSX, lalu map kolom kategori, uraian, volume, satuan, harga.</p>
                    <div class="mt-2 flex gap-2">
                        <input type="file" name="file" class="input !py-1.5 text-xs" accept=".xlsx" required>
                        <button class="btn btn-secondary btn-sm" type="submit">Preview & Mapping</button>
                    </div>
                </form>
            </div>

            @if ($current && (! $current->isFinal() || auth()->user()->isDev()))
                <div class="card card-pad">
                    <div class="section-title">Tambah item RAB</div>
                    <form method="POST" action="{{ route('budget.items.store', $current) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label class="label">Kategori</label>
                            <select name="category" class="input" required data-creatable>
                                <option value="">Pilih kategori…</option>
                                @foreach (\App\Models\Lookup::options('budget_categories', \App\Models\BudgetItem::whereHas('version', fn ($q) => $q->where('event_id', $event->id))->distinct()->limit(100)->pluck('category')->all()) as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Uraian</label>
                            <input name="description" class="input" required placeholder="Sewa booth">
                        </div>
                        <div>
                            <label class="label">Qty</label>
                            <input type="number" step="0.01" name="qty" class="input" required value="1">
                        </div>
                        <div>
                            <label class="label">Satuan</label>
                            <select name="unit" class="input" required data-creatable>
                                @foreach (\App\Models\Lookup::options('units', \App\Models\BudgetItem::query()->whereNotNull('unit')->distinct()->limit(100)->pluck('unit')->all()) as $value => $label)
                                    <option value="{{ $value }}" @selected($value === 'unit')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Harga Satuan</label>
                            <input type="number" step="0.01" name="unit_price" class="input" required value="0">
                        </div>
                        <div>
                            <label class="label">Task Group</label>
                            <select name="task_group_id" class="input">
                                <option value="">Tanpa task group</option>
                                @foreach ($event->taskGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <button class="btn btn-secondary btn-sm" type="submit">Tambah item</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </details>
    @endif

    <div class="card mt-4 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4" style="border-color: var(--border)">
            <div>
                <div class="section-title">Item RAB</div>
                <div class="text-xs" style="color: var(--muted)">Realisasi dihitung dari expense approved.</div>
            </div>
            <div class="text-xs" style="color: var(--muted)">{{ $items->count() }} item</div>
        </div>
        <div class="border-b p-3" style="border-color: var(--border)">
            <input data-table-filter="budgetItemsTable" class="input max-w-xs" placeholder="Cari item RAB…">
        </div>
        <div class="overflow-x-auto">
            <table class="table" id="budgetItemsTable">
                <thead>
                    <tr><th>Kategori</th><th>Uraian</th><th>Qty</th><th>Harga</th><th>Rencana</th><th>Realisasi</th><th>Variance</th><th>Task Group</th></tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr data-search="{{ strtolower($item->category.' '.$item->description.' '.($item->taskGroup?->name ?? '')) }}">
                            <td><span class="badge badge-muted">{{ $item->category }}</span></td>
                            <td class="font-medium">{{ $item->description }}</td>
                            <td>{{ (float) $item->qty }} {{ $item->unit }}</td>
                            <td><x-money :value="$item->unit_price" /></td>
                            <td><x-money :value="$item->planned_amount" /></td>
                            <td><x-money :value="$item->actual_amount" /></td>
                            <td style="color: {{ (float) $item->actual_amount > (float) $item->planned_amount ? 'var(--danger)' : 'var(--success)' }}">
                                <x-money :value="(float) $item->actual_amount - (float) $item->planned_amount" />
                            </td>
                            <td class="text-xs">{{ $item->taskGroup?->name ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-sm" style="color: var(--muted)">Belum ada item RAB.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
