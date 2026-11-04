@extends('layouts.app')

@section('title', 'Expenses')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Expenses</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Inbox expense dan approval lintas event.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('global.export', ['type' => 'expenses'] + request()->query()) }}" class="btn btn-secondary">Export CSV</a>
        <a href="{{ route('global.expenses', ['status' => 'submitted']) }}" class="btn btn-secondary">Inbox Approval</a>
        @if ($creatableEvents->isNotEmpty())
            <button type="button" class="btn btn-primary" data-modal-target="globalCreateExpense">+ Catat Expense</button>
        @endif
    </div>
</div>

<div class="card mb-4 p-4">
    <form method="GET" class="filter-bar">
        <div class="filter-field is-search"><label class="label">Cari</label><input name="search" value="{{ request('search') }}" class="input" placeholder="Deskripsi / vendor…"></div>
        <div class="filter-field"><label class="label">Event</label><select name="event_id" class="input"><option value="">Semua</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->name }}</option>@endforeach</select></div>
        <div class="filter-field"><label class="label">Status</label><select name="status" class="input"><option value="">Semua</option>@foreach (['submitted' => 'Menunggu', 'approved' => 'Approved', 'rejected' => 'Rejected', 'paid' => 'Paid'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Filter</button><a href="{{ route('global.expenses') }}" class="btn btn-secondary">Reset</a></div>
    </form>
</div>

<x-filter-chips
    :keys="['search' => 'Cari', 'status' => 'Status', 'event_id' => 'Event']"
    :names="['event_id' => $events->pluck('name', 'id'), 'status' => ['submitted' => 'Menunggu', 'approved' => 'Approved', 'rejected' => 'Rejected', 'paid' => 'Paid']]"
/>

@php
    $canBulk = $expenses->contains(fn ($expense) => auth()->user()->canApproveExpense($expense->event));
@endphp

@if ($canBulk)
    <form id="bulkExpensesForm" method="POST" action="{{ route('expenses.bulk') }}" data-bulk-form>
        @csrf
    </form>
@endif

<div class="card overflow-hidden">
    @if ($canBulk)
        <div class="flex flex-wrap items-center gap-2 border-b p-3" style="border-color: var(--border)">
            <span class="text-xs" style="color: var(--muted)">Terpilih: <b data-bulk-count>0</b></span>
            <select name="action" form="bulkExpensesForm" class="input !w-auto">
                <option value="approve">Approve terpilih</option>
                <option value="reject">Reject terpilih</option>
            </select>
            <button type="submit" form="bulkExpensesForm" class="btn btn-primary btn-sm" data-bulk-apply disabled>Terapkan</button>
            <span class="text-[11px]" style="color: var(--muted)">Hanya expense berstatus menunggu yang diproses.</span>
        </div>
    @endif
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr>
                @if ($canBulk)<th class="w-8"><input type="checkbox" data-bulk-all aria-label="Pilih semua"></th>@endif
                <th>Tanggal</th><th>Event</th><th>Deskripsi</th><th>Vendor</th><th class="text-right">Amount</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        @if ($canBulk)
                            <td>
                                @if (auth()->user()->canApproveExpense($expense->event) && $expense->status === 'submitted')
                                    <input type="checkbox" form="bulkExpensesForm" data-bulk-item name="expense_ids[]" value="{{ $expense->id }}" aria-label="Pilih {{ $expense->description }}">
                                @endif
                            </td>
                        @endif
                        <td class="whitespace-nowrap">{{ $expense->expense_date?->format('d/m/Y') }}</td>
                        <td><a href="{{ route('events.tab', [$expense->event, 'finance']) }}?view=expenses" class="link text-xs">{{ $expense->event?->name }}</a></td>
                        <td>{{ $expense->description }}</td>
                        <td>{{ $expense->vendor ?: '—' }}</td>
                        <td class="text-right"><x-money :value="$expense->amount" /></td>
                        <td><x-status :status="$expense->status" /></td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @if ((auth()->user()->canCreateExpense($expense->event) || auth()->user()->isDev()) && (in_array($expense->status, ['draft', 'submitted', 'rejected'], true) || auth()->user()->isDev()))
                                    <button type="button" class="btn btn-ghost btn-sm" data-modal-target="globalEditExpense{{ $expense->id }}">Edit</button>
                                @endif
                                @if (auth()->user()->canApproveExpense($expense->event) && $expense->status === 'submitted')
                                    <form method="POST" action="{{ route('expenses.approve', $expense) }}">@csrf<input type="hidden" name="lock_version" value="{{ $expense->lock_version }}"><button class="btn btn-secondary btn-sm">Approve</button></form>
                                    <form method="POST" action="{{ route('expenses.reject', $expense) }}">@csrf<input type="hidden" name="lock_version" value="{{ $expense->lock_version }}"><button class="btn btn-ghost btn-sm">Reject</button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canBulk ? 8 : 7 }}" class="text-center text-sm" style="color: var(--muted)">Tidak ada expense.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $expenses->links() }}</div>

@if ($creatableEvents->isNotEmpty())
    <x-modal id="globalCreateExpense" title="Catat Expense" size="xl">
        @include('events.partials.expense-form', ['events' => $creatableEvents, 'expense' => null, 'event' => null])
    </x-modal>
@endif

@foreach ($expenses as $expense)
    @if ((auth()->user()->canCreateExpense($expense->event) || auth()->user()->isDev()) && (in_array($expense->status, ['draft', 'submitted', 'rejected'], true) || auth()->user()->isDev()))
        <x-modal id="globalEditExpense{{ $expense->id }}" title="Edit Expense" size="lg">
            @include('events.partials.expense-form', ['event' => $expense->event, 'expense' => $expense])
        </x-modal>
    @endif
@endforeach
@endsection
