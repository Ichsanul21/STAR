@props(['name' => 'dot', 'class' => 'w-5 h-5'])

@php
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
        'tasks' => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7.5 12.5l2.5 2.5 5-5.5"/>',
        'events' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/><circle cx="8.5" cy="14.5" r="1"/><circle cx="12" cy="14.5" r="1"/>',
        'clients' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 20c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><circle cx="17" cy="9" r="2.2"/><path d="M15.5 15.4c1.2-.9 2.6-1.2 3.8-.8 1.4.5 2.2 1.6 2.2 3.1"/>',
        'budget' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'expenses' => '<path d="M6 3h12a1 1 0 0 1 1 1v17l-3-2-2 2-2-2-2 2-2-2-3 2V4a1 1 0 0 1 1-1z"/><path d="M8 8h8M8 12h5"/>',
        'payments' => '<rect x="2.5" y="5" width="19" height="14" rx="3"/><path d="M2.5 10h19"/><path d="M6 15h4"/>',
        'documents' => '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 16l-5-5L6 20"/>',
        'reports' => '<path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
        'bell' => '<path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 20a2.5 2.5 0 0 0 4 0"/>',
        'conflicts' => '<path d="M12 3l9 16H3z"/><path d="M12 9v5"/><circle cx="12" cy="17" r="0.8" fill="currentColor" stroke="none"/>',
        'offline' => '<path d="M4 7h13l-3-3M20 17H7l3 3"/><path d="M4 12h9"/>',
        'master' => '<rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2"/>',
        'audit' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'integrity' => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'settings' => '<path d="M4 7h9M17 7h3M4 17h4M12 17h8"/><circle cx="15" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h9"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'download' => '<path d="M12 4v10"/><path d="M8 11l4 4 4-4"/><path d="M5 19h14"/>',
        'chevron' => '<path d="M9 6l6 6-6 6"/>',
        'arrow' => '<path d="M7 17L17 7"/><path d="M9 7h8v8"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $paths[$name] ?? $paths['dot'] !!}
</svg>
