@php
    $editing = isset($expense) && $expense;
    $form = $editing ? $expense : null;
    $global = empty($event);
    $events = $events ?? collect();
    $source = $form?->event ?? $event;
    $items = $budgetItems ?? ($source?->finalBudget?->items ?? $source?->planningBudget?->items ?? collect());
    $action = $editing
        ? route('expenses.update', $expense)
        : ($global ? route('expenses.store-global') : route('expenses.store', $event));
    $categories = \App\Models\Lookup::options(
        'expense_categories',
        \App\Models\Expense::query()->whereNotNull('category')->distinct()->limit(100)->pluck('category')->all()
    );
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-3" @if(! $editing) data-offline-form data-offline-label="Expense" @endif>
    @csrf
    @if ($editing)
        @method('PATCH')
        <input type="hidden" name="lock_version" value="{{ $expense->lock_version }}">
        <x-merge-base :model="$expense" :fields="['expense_date', 'category', 'description', 'qty', 'unit_price', 'vendor', 'payment_method', 'paid_by', 'notes', 'budget_item_id']" />
    @endif
    @if ($global && ! $editing)
        <div class="md:col-span-3">
            <label class="label">Event</label>
            <select name="event_id" class="input" required data-expense-event data-budget-base="{{ url('/events') }}">
                <option value="">Pilih event…</option>
                @foreach ($events as $option)
                    <option value="{{ $option->id }}" @selected(old('event_id', $events->count() === 1 ? $option->id : null) == $option->id)>
                        {{ $option->name }} · {{ $option->dealer?->name }} · {{ $option->status }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label class="label">Tanggal</label>
        <input type="date" name="expense_date" value="{{ old('expense_date', $form?->expense_date?->toDateString() ?? now()->toDateString()) }}" class="input" required>
    </div>
    <div>
        <label class="label">Kategori</label>
        <select name="category" class="input" required data-creatable>
            <option value="">Pilih kategori…</option>
            @foreach ($categories as $value => $label)
                <option value="{{ $value }}" @selected(old('category', $form?->category) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Budget Item</label>
        <select name="budget_item_id" class="input" data-expense-budget-items>
            <option value="">Unbudgeted</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" @selected(old('budget_item_id', $form?->budget_item_id) == $item->id)>{{ $item->category }} · {{ $item->description }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="label">Deskripsi</label>
        <input name="description" value="{{ old('description', $form?->description) }}" class="input" required placeholder="Pembayaran vendor booth">
    </div>
    <div>
        <label class="label">Vendor</label>
        <input name="vendor" value="{{ old('vendor', $form?->vendor) }}" class="input" placeholder="PT Vendor" data-autocomplete data-remote="{{ route('lookup.vendors') }}" data-creatable>
    </div>
    <div>
        <label class="label">Qty</label>
        <input type="number" step="0.01" name="qty" value="{{ old('qty', $form?->qty ?? 1) }}" class="input" required>
    </div>
    <div>
        <label class="label">Harga Satuan</label>
        <input type="number" step="0.01" name="unit_price" value="{{ old('unit_price', $form?->unit_price ?? 0) }}" class="input" required>
    </div>
    <div>
        <label class="label">Payment Method</label>
        <select name="payment_method" class="input">
            @foreach (['transfer' => 'Transfer', 'cash' => 'Cash', 'card' => 'Card', 'petty_cash' => 'Petty Cash'] as $value => $label)
                <option value="{{ $value }}" @selected(old('payment_method', $form?->payment_method ?? 'transfer') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Paid By</label>
        <input name="paid_by" value="{{ old('paid_by', $form?->paid_by) }}" class="input" placeholder="Nama PIC" data-autocomplete data-remote="{{ route('lookup.users') }}" data-creatable>
    </div>
    <div>
        <label class="label">Receipt {{ $editing ? '(kosongkan jika tidak diganti)' : '' }}</label>
        <input type="file" name="receipt" class="input" accept=".jpg,.jpeg,.png,.pdf">
        @if ($form?->receipt_path)
            <a href="{{ route('expenses.receipt', $form) }}" data-preview="{{ str_ends_with(strtolower($form->receipt_path), '.pdf') ? 'pdf' : 'image' }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::expense($form)) }}" class="link mt-1 inline-block text-[11px]">Lihat receipt</a>
        @endif
    </div>
    <div class="md:col-span-3">
        <label class="label">Catatan</label>
        <input name="notes" value="{{ old('notes', $form?->notes) }}" class="input">
    </div>
    <div class="md:col-span-3">
        <button class="btn btn-primary btn-sm" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Simpan Expense' }}</button>
    </div>
</form>
