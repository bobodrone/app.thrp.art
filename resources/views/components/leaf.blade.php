@props([
    'size' => 60,
    'color' => '#1A5C38',
    'veinColor' => '#124029',
    'rotate' => 0,
])

@php
    $w  = $size * 0.6;
    $h  = $size;
    $cx = $size * 0.3;
@endphp

<svg
    width="{{ $w }}"
    height="{{ $h }}"
    viewBox="0 0 {{ $w }} {{ $h }}"
    style="transform: rotate({{ $rotate }}deg)"
    xmlns="http://www.w3.org/2000/svg"
    {{ $attributes->merge(['aria-hidden' => 'true']) }}
>
    <path
        d="M{{ $cx }},{{ $h * 0.05 }}
           C{{ $w * 0.88 }},{{ $h * 0.2 }} {{ $w * 0.95 }},{{ $h * 0.6 }} {{ $cx }},{{ $h * 0.95 }}
           C{{ $w * 0.05 }},{{ $h * 0.6 }} {{ $w * 0.12 }},{{ $h * 0.2 }} {{ $cx }},{{ $h * 0.05 }}Z"
        fill="{{ $color }}"
    />
    <line
        x1="{{ $cx }}" y1="{{ $h * 0.08 }}"
        x2="{{ $cx }}" y2="{{ $h * 0.9 }}"
        stroke="{{ $veinColor }}"
        stroke-width="{{ $size * 0.022 }}"
        stroke-linecap="round"
    />
</svg>