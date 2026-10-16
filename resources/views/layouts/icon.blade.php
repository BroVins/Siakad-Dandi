@php
$icons = [
'home'=>'M3 10 12 3l9 7M5 9v12h5v-7h4v7h5V9',
'user'=>'M20 21v-2a7 7 0 0 0-14 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
'book'=>'M4 3h6a3 3 0 0 1 3 3v15a4 4 0 0 0-4-2H4zm16 0h-4a3 3 0 0 0-3 3m0 15a4 4 0 0 1 4-2h3V3',
'calendar'=>'M4 5h16v16H4zM8 3v4m8-4v4M4 10h16m-12 4h3m2 0h3m-8 3h3',
'chart'=>'M4 21V11h4v10m2 0V6h4v15m2 0V2h4v19',
'card'=>'M3 5h18v14H3zM3 10h18M6 15h4',
'cap'=>'m2 8 10-5 10 5-10 5zm4 2v7c3 3 9 3 12 0v-7m4-2v9',
'file'=>'M6 3h8l4 4v14H6zM14 3v5h4M9 12h6m-6 4h6',
'check'=>'m5 12 4 4L19 6',
'clock'=>'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0M12 6v6l4 2',
'arrow'=>'M4 12h16m-6-6 6 6-6 6',
'logout'=>'M9 3H4v18h5m5-16 7 7-7 7m-5-7h12',
'menu'=>'M3 6h18M3 12h18M3 18h18',
'bell'=>'M18 8a6 6 0 0 0-12 0v7l-2 3h16l-2-3zm-9 13h6',
'grid'=>'M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z',
];
@endphp
<svg class="icon {{ $class ?? '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$name ?? 'file'] ?? $icons['file'] }}"/></svg>
