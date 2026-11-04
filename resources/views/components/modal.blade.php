@props(['id', 'title' => null, 'size' => 'md', 'open' => false])

<dialog id="{{ $id }}" class="app-modal app-modal-{{ $size }}" @if($open) data-auto-open @endif>
    <div class="app-modal-panel">
        <div class="app-modal-head">
            <div class="min-w-0">
                <div class="text-sm font-bold" style="color: var(--ink)">{{ $title }}</div>
                @isset($subtitle)
                    <div class="mt-0.5 text-xs" style="color: var(--muted)">{{ $subtitle }}</div>
                @endisset
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-modal-close aria-label="Tutup">✕</button>
        </div>
        <div class="app-modal-body">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="app-modal-foot">{{ $footer }}</div>
        @endisset
    </div>
</dialog>
