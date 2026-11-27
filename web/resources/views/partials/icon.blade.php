{{-- Icon SVG nét mảnh 24x24, cùng bộ với icon ở trang bản đồ (stroke currentColor). Dùng: @include('partials.icon', ['name' => 'map']) --}}
@php
    $paths = [
        'map' => '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14M15 6v14"/>',
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M19 19v2H6"/>',
        'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>',
        'chart' => '<path d="M4 20V4M4 20h16"/><path d="m7 15 4-5 3 3 5-6"/>',
        'settings' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
        'login' => '<path d="M14 4h5v16h-5M4 12h11M11 8l4 4-4 4"/>',
        'logout' => '<path d="M10 4H5v16h5M9 12h11M16 8l4 4-4 4"/>',
        'home' => '<path d="m4 11 8-7 8 7"/><path d="M6 10v10h12V10M10 20v-5h4v5"/>',
        'target' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
        'database' => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3"/>',
        'ruler' => '<path d="m4 16 12-12 4 4L8 20l-4-4Z"/><path d="m8 12 2 2M11 9l2 2M14 6l2 2"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'alert' => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v5M12 18v.5"/>',
        'download' => '<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>',
        'link' => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17v.5"/>',
        'keyboard' => '<rect x="3" y="6" width="18" height="12" rx="1"/><path d="M7 10h.5M11 10h.5M15 10h.5M7 14h10"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11M4 6h.5M4 12h.5M4 18h.5"/>',
        'code' => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/>',
        'server' => '<rect x="4" y="4" width="16" height="6" rx="1"/><rect x="4" y="14" width="16" height="6" rx="1"/><path d="M8 7h.5M8 17h.5"/>',
        'flask' => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4a2 2 0 0 0 1.8-3l-5-9V3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
    ];
    $cls = $class ?? 'ico';
@endphp
<svg class="{{ $cls }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $paths[$name] ?? $paths['info'] !!}</svg>
