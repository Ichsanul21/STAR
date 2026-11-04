@php
    $items = $budget?->items ?? collect();
    $approved = (float) $event->expenses->whereIn('status', ['approved', 'paid', 'reimbursed'])->sum('amount');
    $committed = (float) $event->expenses->whereIn('status', ['submitted', 'approved', 'paid', 'reimbursed'])->sum('amount');
    $editableStatuses = ['draft', 'submitted', 'rejected'];
@endphp

<section id="expenses" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Expenses</h2>
            <p class="text-sm" style="color: var(--muted)">Pencatatan pengeluaran event dan approval.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-success">Approved <x-money :value="$approved" /></span>
            <span class="badge badge-warning">Committed <x-money :value="$committed" /></span>
            @if (auth()->user()->canCreateExpense($event))
                <button type="button" class="btn btn-primary btn-sm" data-modal-target="createExpenseModal">+ Catat Expense</button>
            @endif
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap gap-2 border-b p-3" style="border-color: var(--border)">
            <input data-table-filter="expensesTable" class="input max-w-xs" placeholder="Cari expense…">
            <select data-table-filter-status="expensesTable" class="input !w-auto">
                <option value="">Semua status</option>
                @foreach (['submitted' => 'Menunggu', 'approved' => 'Approved', 'rejected' => 'Rejected', 'paid' => 'Paid'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="table" id="expensesTable">
                <thead>
                    <tr><th>Tanggal</th><th>Kategori</th><th>Deskripsi</th><th>Vendor</th><th>Amount</th><th>Status</th><th>Receipt</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($event->expenses->sortByDesc('expense_date') as $expense)
                        <tr data-search="{{ strtolower($expense->description.' '.$expense->vendor.' '.$expense->category) }}" data-status="{{ $expense->status }}">
                            <td class="whitespace-nowrap">{{ $expense->expense_date?->format('d/m/Y') }}</td>
                            <td><span class="badge badge-muted">{{ $expense->category }}</span></td>
                            <td>
                                <div class="font-medium">{{ $expense->description }}</div>
                                <div class="text-xs" style="color: var(--muted)">{{ $expense->budgetItem?->description }}</div>
                            </td>
                            <td>{{ $expense->vendor ?: '—' }}</td>
                            <td class="whitespace-nowrap font-semibold"><x-money :value="$expense->amount" /></td>
                            <td><x-status :status="$expense->status" /></td>
                            <td>
                                @if ($expense->receipt_path)
                                    <a href="{{ route('expenses.receipt', $expense) }}" data-preview="{{ str_ends_with(strtolower($expense->receipt_path), '.pdf') ? 'pdf' : 'image' }}" data-preview-title="Receipt {{ $expense->description }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::expense($expense)) }}" class="link text-xs">Lihat</a>
                                @else
                                    <span class="text-xs" style="color: var(--muted)">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if ((auth()->user()->canCreateExpense($event) || auth()->user()->isDev()) && (in_array($expense->status, $editableStatuses, true) || auth()->user()->isDev()))
                                        <button type="button" class="btn btn-ghost btn-sm" data-modal-target="editExpense{{ $expense->id }}">Edit</button>
                                    @endif
                                    @if (auth()->user()->canApproveExpense($event) && $expense->status === 'submitted')
                                        <form method="POST" action="{{ route('expenses.approve', $expense) }}">@csrf<input type="hidden" name="lock_version" value="{{ $expense->lock_version }}"><button class="btn btn-secondary btn-sm">Approve</button></form>
                                        <form method="POST" action="{{ route('expenses.reject', $expense) }}">@csrf<input type="hidden" name="lock_version" value="{{ $expense->lock_version }}"><button class="btn btn-ghost btn-sm">Reject</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-sm" style="color: var(--muted)">Belum ada expense.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (auth()->user()->canCreateExpense($event))
        <x-modal id="createExpenseModal" title="Catat Expense" size="lg">
            @include('events.partials.expense-form', ['event' => $event, 'budgetItems' => $items])
        </x-modal>

        @foreach ($event->expenses as $expense)
            @if (in_array($expense->status, $editableStatuses, true) || auth()->user()->isDev())
                <x-modal id="editExpense{{ $expense->id }}" title="Edit Expense" size="lg">
                    @include('events.partials.expense-form', ['event' => $event, 'expense' => $expense, 'budgetItems' => $items])
                </x-modal>
            @endif
        @endforeach
    @endif
</section>
