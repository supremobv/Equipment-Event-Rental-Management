@props(['name'=>'box'])
<svg {{ $attributes->merge(['class'=>'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('grid')<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>@break
@case('clipboard')<rect x="5" y="5" width="14" height="16" rx="2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2"/>@break
@case('box')<path d="m12 3 9 5v9l-9 5-9-5V8l9-5ZM3 8l9 5 9-5M12 13v9M7.5 5.5l9 5"/>@break
@case('layers')<path d="m12 3 10 5-10 5L2 8l10-5ZM2 12l10 5 10-5M2 16l10 5 10-5"/>@break
@case('users')<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6M21 21v-3a6 6 0 0 0-4-5"/>@break
@case('tool')<path d="M14.5 6.5 18 3a6 6 0 0 0-7.5 7.5l-7 7a2 2 0 0 0 3 3l7-7A6 6 0 0 0 21 6l-3.5 3.5-3-3Z"/>@break
@case('chart')<path d="M3 3v18h18M7 16v-5M12 16V7M17 16v-8"/>@break
@case('shield')<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>@break
@case('settings')<path d="m9 3-1 3-3 1-2 3 2 2v3l3 2 1 4h6l1-4 3-2v-3l2-2-2-3-3-1-1-3H9Z"/><circle cx="12" cy="12" r="3"/>@break
@case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
@case('logout')<path d="M9 4H4v16h5M14 8l4 4-4 4M8 12h12"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('search')<circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/>@break
@case('arrow')<path d="M4 12h16m-6-6 6 6-6 6"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@case('money')<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M5 9h1m12 6h1"/>@break
@case('truck')<path d="M3 5h12v12H3V5Zm12 5h4l3 4v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>@break
@case('download')<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@default<circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/>
@endswitch</svg>
