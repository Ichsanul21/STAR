@extends('layouts.app')

@section('title', 'Documents')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-extrabold tracking-tight">Documents</h1>
    <p class="mt-1 text-sm" style="color: var(--muted)">Galeri dokumentasi lintas event.</p>
</div>

<x-filter-chips
    :keys="['search' => 'Cari', 'event_id' => 'Event']"
    :names="['event_id' => $events->pluck('name', 'id')]"
/>

<div class="card mb-4 p-4">
    <form method="GET" class="filter-bar">
        <div class="filter-field is-search"><label class="label">Cari</label><input name="search" value="{{ request('search') }}" class="input" placeholder="Caption / alamat…"></div>
        <div class="filter-field"><label class="label">Event</label><select name="event_id" class="input"><option value="">Semua</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->name }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Filter</button><a href="{{ route('global.documents') }}" class="btn btn-secondary">Reset</a></div>
    </form>
</div>

<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
    @forelse ($media as $item)
        <div class="card overflow-hidden">
            <a href="{{ route('media.file', $item) }}" data-preview="image" data-preview-title="{{ $item->caption ?: 'Dokumentasi' }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::media($item)) }}" class="block aspect-square bg-slate-100">
                <img src="{{ route('media.file', $item) }}" alt="{{ $item->caption }}" class="h-full w-full object-cover">
            </a>
            <div class="p-3">
                <div class="truncate text-xs font-semibold">{{ $item->caption ?: 'Tanpa caption' }}</div>
                <div class="mt-1 truncate text-[10px]" style="color: var(--muted)">{{ $item->event?->name }} · {{ $item->phase?->name }}</div>
            </div>
        </div>
    @empty
        <div class="col-span-full"><x-empty title="Belum ada dokumentasi" body="Upload foto dari halaman event." /></div>
    @endforelse
</div>

<div class="mt-4">{{ $media->links() }}</div>
@endsection
