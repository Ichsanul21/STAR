@extends('layouts.app')

@section('title', 'Offline Queue')

@section('content')
<div data-offline-page>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight">Offline Queue</h1>
            <p class="mt-1 text-sm" style="color: var(--muted)">Antrean form yang tersimpan di perangkat saat offline, termasuk konflik versi.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs" style="color: var(--muted)" data-offline-summary>0 antrean</span>
            <button type="button" class="btn btn-primary" data-offline-sync-all>Kirim Semua</button>
        </div>
    </div>

    <div data-offline-toast></div>

    <div class="card mb-4 p-4 text-sm" style="color: var(--muted)">
        Antrean disimpan di IndexedDB perangkat ini. Item berkonflik tidak dikirim otomatis — gunakan
        <span class="font-semibold" style="color: var(--ink)">Gabung</span> untuk membandingkan dengan data server terbaru,
        lalu kirim ulang. Item yang ditolak validasi (422) otomatis dihapus.
    </div>

    <div class="hidden" data-offline-empty>
        <x-empty title="Antrean kosong" body="Semua data sudah terkirim. Antrean baru muncul saat kamu menyimpan form ketika offline." />
    </div>

    <div class="grid gap-3" data-offline-list></div>
</div>

<x-modal id="offlineMergeModal" title="Gabung Data" size="xl">
    <div id="offlineMergeBody"></div>
    <x-slot name="footer">
        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
            <button type="button" class="btn btn-primary" id="offlineMergeSave">Simpan ke Antrean</button>
        </div>
    </x-slot>
</x-modal>
@endsection
