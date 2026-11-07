<div class="mb-3">
    <h2 class="text-xl font-extrabold">Data</h2>
    <p class="text-sm" style="color: var(--muted)">Jumlah record dan hard delete untuk pengujian.</p>
</div>

<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($counts as $label => $total)
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">{{ str_replace('_', ' ', $label) }}</div>
            <div class="mt-1 text-2xl font-extrabold">{{ number_format($total) }}</div>
        </div>
    @endforeach
</div>

<div class="card card-pad mt-4">
    <div class="section-title">Hapus record</div>
    <p class="mt-1 text-xs" style="color: var(--muted)">Hard delete untuk pengujian. Gunakan dengan hati-hati.</p>
    <form method="POST" action="#" class="mt-3 grid gap-3 sm:grid-cols-3" onsubmit="this.action = '/dev/records/' + this.type.value + '/' + this.record_id.value; this.querySelector('[name=_method]').disabled = false;">
        @csrf
        @method('DELETE')
        <div>
            <label class="label">Tipe</label>
            <select name="type" class="input" required>
                @foreach (['event' => 'Event', 'task' => 'Task', 'expense' => 'Expense', 'invoice' => 'Invoice', 'report' => 'Report', 'media' => 'Media', 'dealer' => 'Dealer', 'user' => 'User'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">ID</label>
            <input name="record_id" type="number" min="1" class="input" required>
        </div>
        <div>
            <label class="label">Konfirmasi</label>
            <input name="confirmation" class="input" placeholder="DELETE" required>
        </div>
        <div class="sm:col-span-3">
            <button class="btn btn-secondary" type="submit" onclick="return confirm('Hapus record ini secara permanen?')">Hapus Record</button>
        </div>
    </form>
</div>
