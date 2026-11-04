@extends('layouts.portal')

@section('title', 'Dealer Portal')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <div class="text-xs font-bold uppercase tracking-[0.18em]" style="color: var(--muted)">{{ $events->first()?->dealer?->name ?? 'Dealer' }}</div>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">Laporan Event</h1>
        <p class="mt-1 text-sm text-slate-500">Laporan yang sudah dipublikasikan oleh EO.</p>
    </div>
    <div class="rounded-2xl px-4 py-3 text-sm" style="background: var(--brand-soft); color: var(--brand)">
        <div class="font-bold">{{ $reports->count() }} laporan</div>
        <div class="text-xs">Siap diunduh</div>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-3">
    <div class="card card-pad">
        <div class="text-xs font-semibold text-slate-500">Total Invoice</div>
        <div class="mt-1 text-2xl font-extrabold text-slate-900">Rp{{ number_format($invoices->sum('amount'), 0, ',', '.') }}</div>
    </div>
    <div class="card card-pad">
        <div class="text-xs font-semibold text-slate-500">Sudah Dibayar</div>
        <div class="mt-1 text-2xl font-extrabold text-green-700">Rp{{ number_format($invoices->sum(fn ($i) => $i->paidAmount()), 0, ',', '.') }}</div>
    </div>
    <div class="card card-pad">
        <div class="text-xs font-semibold text-slate-500">Outstanding</div>
        <div class="mt-1 text-2xl font-extrabold" style="color: var(--danger)">Rp{{ number_format($invoices->sum(fn ($i) => $i->balance()), 0, ',', '.') }}</div>
    </div>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <h2 class="text-lg font-extrabold text-slate-900">Laporan</h2>
        @forelse ($reports as $report)
            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold text-slate-500">{{ $report->event->code }} · v{{ $report->version }}</div>
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ $report->event->name }}</div>
                        <div class="text-sm text-slate-500">{{ $report->event->location ?: '—' }} · {{ $report->event->start_date?->format('d/m/Y') }}</div>
                        <div class="mt-2 flex items-center gap-2">
                            <x-status :status="$report->status" />
                            @if ($report->acknowledged_at)
                                <span class="badge badge-success">Diterima</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($report->pdf_path)
                            <a href="{{ route('reports.pdf', $report) }}" class="btn btn-primary btn-sm" data-preview="pdf" data-preview-title="{{ $report->event->name }} v{{ $report->version }}" data-download-url="{{ route('reports.pdf', $report) }}">Lihat PDF</a>
                        @endif
                        @if ($report->xlsx_path)
                            <a href="{{ route('reports.xlsx', $report) }}" class="btn btn-secondary btn-sm">Excel</a>
                        @endif
                        @if (! $report->acknowledged_at)
                            <form method="POST" action="{{ route('reports.acknowledge', $report) }}">
                                @csrf
                                <button class="btn btn-secondary btn-sm">Acknowledge</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <x-empty title="Belum ada laporan" body="Laporan akan muncul setelah EO mempublikasikan hasil event." />
        @endforelse
    </div>

    <div class="space-y-4">
        <h2 class="text-lg font-extrabold text-slate-900">Invoice</h2>
        @forelse ($invoices as $invoice)
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-bold text-slate-900">{{ $invoice->invoice_number }}</div>
                        <div class="text-xs text-slate-500">{{ $invoice->term }} · {{ $invoice->event?->name }}</div>
                    </div>
                    <x-status :status="$invoice->derivedStatus()" />
                </div>
                <div class="mt-3 flex justify-between text-sm">
                    <span class="text-slate-500">Sisa</span>
                    <span class="font-bold">Rp{{ number_format($invoice->balance(), 0, ',', '.') }}</span>
                </div>
            </div>
        @empty
            <div class="card card-pad text-sm text-slate-500">Belum ada invoice.</div>
        @endforelse

        <h2 class="pt-2 text-lg font-extrabold text-slate-900">Notifikasi</h2>
        <div class="card card-pad">
            @forelse ($notifications as $notification)
                <div class="border-b py-2 last:border-0">
                    <div class="text-sm font-semibold {{ $notification->read_at ? 'text-slate-500' : 'text-slate-900' }}">{{ $notification->title }}</div>
                    <div class="text-xs text-slate-500">{{ $notification->body }}</div>
                </div>
            @empty
                <div class="text-sm text-slate-500">Tidak ada notifikasi.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
