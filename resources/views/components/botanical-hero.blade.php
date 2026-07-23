<div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">

    {{-- Large sunflower — top right --}}
    <svg width="220" height="220" viewBox="0 0 220 220"
        class="absolute -right-8 -top-8 opacity-90"
        xmlns="http://www.w3.org/2000/svg">
        @for ($i = 0; $i < 12; $i++)
            <ellipse cx="110" cy="32" rx="12" ry="40"
                transform="rotate({{ $i * 30 }} 110 110)"
                fill="#1A5C38" />
        @endfor
        <circle cx="110" cy="110" r="36" fill="#124029"/>
        <circle cx="110" cy="110" r="18" fill="#FFD600"/>
        @for ($i = 0; $i < 8; $i++)
            @php $a = $i * 45; @endphp
            <circle
                cx="{{ 110 + 26 * cos(deg2rad($a - 90)) }}"
                cy="{{ 110 + 26 * sin(deg2rad($a - 90)) }}"
                r="4" fill="#1A5C38" opacity="0.6"/>
        @endfor
    </svg>

    {{-- Medium poppy — left mid --}}
    <svg width="140" height="140" viewBox="0 0 140 140"
        class="absolute -left-4 top-1/3 opacity-85"
        xmlns="http://www.w3.org/2000/svg">
        @for ($i = 0; $i < 5; $i++)
            <ellipse cx="70" cy="22" rx="10" ry="28"
                transform="rotate({{ $i * 72 }} 70 70)"
                fill="#C1121F" />
        @endfor
        <circle cx="70" cy="70" r="20" fill="#1A1209"/>
        <circle cx="70" cy="70" r="9"  fill="#FFD600"/>
    </svg>

    {{-- Small blue flower — bottom right --}}
    <svg width="90" height="90" viewBox="0 0 90 90"
        class="absolute bottom-8 right-24 opacity-80"
        xmlns="http://www.w3.org/2000/svg">
        @for ($i = 0; $i < 6; $i++)
            <ellipse cx="45" cy="12" rx="6" ry="16"
                transform="rotate({{ $i * 60 }} 45 45)"
                fill="#1565C0" />
        @endfor
        <circle cx="45" cy="45" r="13" fill="#0D4FA3"/>
        <circle cx="45" cy="45" r="6"  fill="#FFD600"/>
    </svg>

    {{-- Leaf cluster — bottom left --}}
    <svg width="180" height="200" viewBox="0 0 180 200"
        class="absolute -bottom-6 -left-6 opacity-80"
        xmlns="http://www.w3.org/2000/svg">
        <path d="M90,200 C85,160 70,120 80,80" stroke="#1A5C38" stroke-width="4" fill="none" stroke-linecap="round"/>
        <path d="M80,130 C50,110 30,90 40,65 C65,80 78,105 80,130Z"   fill="#2D8B55"/>
        <path d="M80,105 C110,85  130,65 120,40 C95,55 82,80  80,105Z" fill="#1A5C38"/>
        <path d="M80,160 C55,150  35,135 38,110 C62,120 76,142 80,160Z" fill="#2D8B55" opacity="0.8"/>
        <path d="M80,80 C68,55 70,30 80,10 C90,30 92,55 80,80Z" fill="#3DAA6B"/>
    </svg>

    {{-- Scattered small leaves — top left area --}}
    <svg width="120" height="120" viewBox="0 0 120 120"
        class="absolute left-1/3 top-4 opacity-60"
        xmlns="http://www.w3.org/2000/svg">
        <path d="M40,80 C28,60 30,35 40,15 C50,35 52,60 40,80Z" fill="#1A5C38" transform="rotate(-20 40 47)"/>
        <path d="M80,90 C68,70 70,45 80,25 C90,45 92,70 80,90Z" fill="#2D8B55" transform="rotate(15 80 57)" opacity="0.8"/>
    </svg>

    {{-- Small yellow accent flower — centre top --}}
    <svg width="70" height="70" viewBox="0 0 70 70"
        class="absolute top-6 left-1/2 -translate-x-1/2 opacity-70"
        xmlns="http://www.w3.org/2000/svg">
        @for ($i = 0; $i < 8; $i++)
            <ellipse cx="35" cy="9" rx="5" ry="12"
                transform="rotate({{ $i * 45 }} 35 35)"
                fill="#FFD600" />
        @endfor
        <circle cx="35" cy="35" r="11" fill="#1A5C38"/>
        <circle cx="35" cy="35" r="5"  fill="#FFD600"/>
    </svg>
</div>