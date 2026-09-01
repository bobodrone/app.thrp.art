@props([
    'url',
    'alt'   => 'Image attached to this response',
    // false: full width, height follows the image — nothing cropped, no
    //        letterboxing, but a tall portrait image renders tall.
    // true:  full width in a fixed 16/9 frame filled with object-cover —
    //        uniform height, at the cost of cropping the overflowing edges.
    'cover' => false,
])

<figure {{ $attributes->merge(['class' => 'mb-4']) }}>
    <img
        src="{{ $url }}"
        alt="{{ $alt }}"
        loading="lazy"
        @class([
            'w-full rounded-xl border border-leaf-200',
            'aspect-video object-cover' => $cover,
            'h-auto'                    => ! $cover,
        ])
    >
</figure>
