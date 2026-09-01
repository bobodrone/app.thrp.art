@props([
    'answer',           // App\Models\Answer
    'rendered',         // its body, already through the markdown renderer
    'viewer' => null,   // who is looking — decides how the credit reads
])

@if ($imageUrl = $answer->imageUrl())
    <x-answer-image :url="$imageUrl" />
@endif

<div class="prose prose-sm max-w-none font-body prose-headings:font-display prose-a:text-leaf-600">
    {!! $rendered !!}
</div>

@if ($answer->authorNameFor($viewer))
    <p class="mt-4 font-body text-xs text-soil-400">
        Response by <x-answerer-name :answer="$answer" :viewer="$viewer" class="font-medium text-soil-600" />
        @if ($answer->published_at)&nbsp;· {{ $answer->published_at->diffForHumans() }}@endif
    </p>
@endif
