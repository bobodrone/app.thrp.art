@props([
    'creator',
    'size' => 'sm',   // sm: table row · lg: profile header
])

@php
    // Fixed class strings — Tailwind only compiles classes it can see verbatim.
    $box = $size === 'lg'
        ? 'h-28 w-28 text-3xl'
        : 'h-11 w-11 text-sm';

    $url = $creator->avatarUrl();
@endphp

@if ($url)
    <img
        src="{{ $url }}"
        alt="{{ $creator->name }}"
        loading="lazy"
        {{ $attributes->merge(['class' => $box.' shrink-0 rounded-full border border-leaf-200 bg-soil-50 object-cover']) }}
    >
@else
    <span
        aria-hidden="true"
        {{ $attributes->merge(['class' => $box.' flex shrink-0 items-center justify-center rounded-full border border-leaf-200 bg-leaf-100 font-display font-bold text-leaf-700']) }}
    >{{ $creator->initials() }}</span>
@endif
