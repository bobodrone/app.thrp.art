@props([
    'size' => 80,
    'petals' => 6,
    'petalColor' => '#FFD600',
    'centerColor' => '#1A5C38',
    'dotColor' => '#FFD600',
])

@php
    $cx      = $size / 2;
    $cy      = $size / 2;
    $ry      = $size * 0.23;
    $rx      = $size * 0.092;
    $petalCy = $size / 2 - $size * 0.23 + $size * 0.08;
    $centerR = $size * 0.17;
    $dotR    = $size * 0.08;
    $angles  = [];
    for ($i = 0; $i < $petals; $i++) {
        $angles[] = (360 / $petals) * $i;
    }
@endphp

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" {{ $attributes->merge(['aria-hidden' => 'true']) }}>
    @foreach ($angles as $angle)
        <ellipse
            cx="{{ $cx }}"
            cy="{{ $petalCy }}"
            rx="{{ $rx }}"
            ry="{{ $ry }}"
            transform="rotate({{ $angle }} {{ $cx }} {{ $cy }})"
            fill="{{ $petalColor }}"
        />
    @endforeach
    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $centerR }}" fill="{{ $centerColor }}"/>
    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $dotR }}"    fill="{{ $dotColor }}"/>
</svg>