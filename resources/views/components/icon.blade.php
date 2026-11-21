@props(['name' => 'grid'])
@php
    $paths = [
        'grid' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'tasks' => 'M8 5h13 M8 12h13 M8 19h13 M3 5h.01 M3 12h.01 M3 19h.01',
        'plus' => 'M12 5v14 M5 12h14',
        'activity' => 'M3 12h4l3-8 4 16 3-8h4',
        'settings' => 'M4 7h16 M4 17h16 M8 4v6 M16 14v6',
        'user' => 'M20 21v-2a7 7 0 0 0-14 0v2 M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'logout' => 'M9 21H4V3h5 M10 12h11 M17 8l4 4-4 4',
        'clock' => 'M12 8v4l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
        'text' => 'M4 4h16 M12 4v16 M8 20h8 M4 8V4 M20 8V4',
        'file' => 'M14 2H5v20h14V7z M14 2v5h5 M8 12h8 M8 16h8',
        'check' => 'M5 12l4 4L19 6',
        'alert' => 'M12 3 2 21h20z M12 9v4 M12 17h.01',
        'server' => 'M3 3h18v7H3z M3 14h18v7H3z M7 6.5h.01 M7 17.5h.01',
        'database' =>
            'M20 5c0 2-4 3-8 3S4 7 4 5s4-3 8-3 8 1 8 3 M4 5v14c0 2 4 3 8 3s8-1 8-3V5 M4 12c0 2 4 3 8 3s8-1 8-3',
        'queue' => 'M4 4h6v6H4z M14 4h6v6h-6z M4 14h6v6H4z M14 14h6v6h-6z',
        'code' => 'm8 6-6 6 6 6 M16 6l6 6-6 6 M14 3l-4 18',
        'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12 M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'lock' => 'M5 10h14v11H5z M8 10V6a4 4 0 0 1 8 0v4',
        'menu' => 'M4 6h16 M4 12h16 M4 18h16',
        'close' => 'm6 6 12 12 M6 18 18 6',
        'play' => 'm8 4 12 8-12 8z',
        'refresh' => 'M20 8a9 9 0 0 0-15-3L2 8 M2 2v6h6 M4 16a9 9 0 0 0 15 3l3-3 M22 22v-6h-6',
        'upload' => 'M12 16V3 M7 8l5-5 5 5 M4 16v5h16v-5',
    ];
@endphp
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ $paths[$name] ?? $paths['grid'] }}" />
</svg>
