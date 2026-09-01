@php
    /** @var \App\Models\Question $question */
    /** @var string|null  $renderedAnswer */
    $renderedAnswer = $renderedAnswer ?? null;

    // Only the main answer fits on a card; the rest are a nudge to the full page.
    $answerCount = $question->visibleAnswerCount();
    $otherCount  = max($answerCount - 1, 0);
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
                <p class="font-body text-xs font-semibold uppercase tracking-wider text-leaf-600">Response</p>
            </div>
            @if ($imageUrl = $question->answerImageUrl())
                <x-answer-image :url="$imageUrl" />
            @endif
            <div class="prose prose-sm max-w-none font-body prose-headings:font-display prose-a:text-leaf-600">
                {!! $renderedAnswer !!}
            </div>
            @if ($question->answererNameFor(auth()->user()))
                <p class="mt-3 font-body text-xs text-soil-400">
                    Response by <x-answerer-name :answer="$question->primaryAnswer" :viewer="auth()->user()" class="font-medium text-soil-600" />
                    @if ($question->primaryAnswer->published_at)&nbsp;· {{ $question->primaryAnswer->published_at->diffForHumans() }}@endif
                </p>
            @endif

            @if ($otherCount > 0)
                <a href="{{ route('questions.show', $question->id) }}"
                   @click.stop
                   class="mt-3 inline-block font-body text-xs font-semibold text-leaf-600 hover:underline">
                    +{{ $otherCount }} {{ \Illuminate\Support\Str::plural('other response', $otherCount) }} from the community →
                </a>
            @endif
        </div>
    </template>

    {{-- "Has a response" hint when collapsed --}}
    @if ($question->status->value === 'answered' && ! empty($renderedAnswer))
        <p x-show="!expanded" x-cloak
           class="mt-2 flex items-center gap-1.5 font-body text-xs font-medium text-leaf-600">
            <x-flower size="12" petalColor="#FFD600" centerColor="#1A5C38" dotColor="#FFD600" />
            @if ($answerCount > 1)
                {{ $answerCount }} responses — click to read
            @else
                Has a response — click to read
            @endif
        </p>
    @endif

    {{-- Actions when expanded --}}
    <div x-show="expanded" x-cloak class="mt-4 flex items-center justify-between gap-3">
        <a href="{{ route('questions.show', $question->id) }}"
           @click.stop
           class="font-body text-xs font-semibold text-leaf-600 underline underline-offset-2 hover:text-leaf-700">
            View full page →
        </a>

        <x-question-action :question="$question" />
    </div>
</div>