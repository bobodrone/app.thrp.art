@props([
    'title' => '',
    'subtitle' => '',
    'flowerPetal' => '#FFD600',
    'flowerCenter' => '#1A5C38',
    'flowerDot' => '#FFD600',
])

<div class="flex min-h-[calc(100vh-4rem)] items-start justify-center bg-sun-500 px-4 pt-14">
    <div class="w-full max-w-sm">

        <div class="mb-6 flex justify-center">
            <x-flower size="56" :petalColor="$flowerPetal" :centerColor="$flowerCenter" :dotColor="$flowerDot" />
        </div>

        <div class="rounded-2xl bg-white p-8 shadow-xl">
            @if ($title)
                <h1 class="font-display mb-1 text-3xl font-black text-soil-900">{{ $title }}</h1>
            @endif
            @if ($subtitle)
                <p class="font-body mb-6 text-sm text-soil-500">{{ $subtitle }}</p>
            @endif

            {{ $slot }}
        </div>

        @isset($footer)
            <p class="mt-4 text-center font-body text-sm text-soil-800">{{ $footer }}</p>
        @endisset
    </div>
</div>