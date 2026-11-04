@props(['title' => 'Belum ada data', 'body' => null])

<div class="flex flex-col items-center justify-center rounded-2xl border border-dashed px-6 py-10 text-center" style="border-color: var(--border)">
    <div class="mb-2 text-sm font-bold">{{ $title }}</div>
    @if ($body)
        <div class="max-w-md text-sm" style="color: var(--muted)">{{ $body }}</div>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
