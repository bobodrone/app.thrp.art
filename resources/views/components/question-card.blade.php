@php
    /** @var \App\Models\Question $question */
    /** @var string|null  $renderedAnswer */
    $renderedAnswer = $renderedAnswer ?? null;
@endphp

<div
    role="button"
    tabindex="0"
    @click="expanded = !expanded"
    @keydown.enter.prevent="expanded = !expanded"
    x-data="{ expanded: false }"
    :class="expanded && 'shadow-xl'"
    class="group cursor-pointer rounded-2xl bg-white p-5 shadow-md transition-all hover:-translate-y-1 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-leaf-600"
>
    {{-- Card top row --}}
    <div class="mb-3 flex items-start justify-between gap-3">
        <x-status-badge :status="$question->status" />
        <span class="shrink-0 font-body text-xs text-soil-400">{{ $question->created_at->diffForHumans() }}</span>
    </div>

    {{-- Question text --}}
    <p x-show="!expanded"
       class="font-display line-clamp-3 text-base font-normal leading-snug text-soil-900">
        {{ $question->content }}
    </p>
    <p x-show="expanded" x-cloak
       class="font-display text-base font-normal leading-snug text-soil-900">
        {{ $question->content }}
    </p>

    {{-- Asker --}}
    @if ($question->asker)
        <p class="mt-2 font-body text-xs text-soil-400">Asked by {{ $question->asker->name }}</p>
    @endif

    {{-- Expanded answer block --}}
    <template x-if="expanded && {{ !empty($renderedAnswer) ? 'true' : 'false' }}">
        <div class="mt-4 border-t-2 border-sun-300 pt-4">
            <div class="mb-2 flex items-center gap-2">
                <x-flower size="16" petalColor="#FFD600" centerColor="#1A5C38" dotColor="#FFD600" />
                <p class="font-body text-xs font-semibold uppercase tracking-wider text-leaf-600">Answer</p>
            </div>
            @if ($imageUrl = $question->answerImageUrl())
                <x-answer-image :url="$imageUrl" />
            @endif
            <div class="prose prose-sm max-w-none font-body prose-headings:font-display prose-a:text-leaf-600">
                {!! $renderedAnswer !!}
            </div>
            @if ($question->answerer)
                <p class="mt-3 font-body text-xs text-soil-400">
                    Answered by <span class="font-medium text-soil-600">{{ $question->answerer->name }}</span>
                    @if ($question->answered_at)&nbsp;· {{ $question->answered_at->diffForHumans() }}@endif
                </p>
            @endif
        </div>
    </template>

    {{-- "Has an answer" hint when collapsed --}}
    @if ($question->status->value === 'answered' && ! empty($renderedAnswer))
        <p x-show="!expanded" x-cloak
           class="mt-2 flex items-center gap-1.5 font-body text-xs font-medium text-leaf-600">
            <x-flower size="12" petalColor="#FFD600" centerColor="#1A5C38" dotColor="#FFD600" />
            Has an answer — click to read
        </p>
    @endif

    {{-- View full page link when expanded --}}
    <a x-show="expanded" x-cloak
       href="{{ route('questions.show', $question->id) }}"
       @click.stop
       class="mt-4 inline-block font-body text-xs font-semibold text-leaf-600 underline underline-offset-2 hover:text-leaf-700">
        View full page →
    </a>
</div>