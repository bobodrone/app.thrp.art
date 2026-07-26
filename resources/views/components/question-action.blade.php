@props([
    'question',
    'size' => 'sm',
])

@php
    $user = auth()->user();

    $classes = 'inline-block shrink-0 rounded-lg bg-leaf-600 font-body font-semibold text-white hover:bg-leaf-500 '
        . ($size === 'md' ? 'px-4 py-2 text-sm' : 'px-3 py-1.5 text-xs');
@endphp

{{-- Whatever this viewer may do with this question next — which for most viewers is nothing. --}}
@if ($question->isClaimableBy($user))
    <form method="post" action="{{ route('creator.questions.claim', $question) }}" @click.stop class="shrink-0">
        @csrf
        <button type="submit" class="{{ $classes }}">Claim &amp; answer →</button>
    </form>
@elseif ($question->isAwaitingAnswerFrom($user))
    {{-- Already theirs — straight back to the answer form. --}}
    <a href="{{ route('creator.questions.show', $question) }}" @click.stop class="{{ $classes }}">Answer →</a>
@elseif ($question->isAnswerableBy($user))
    {{-- Answered by someone else, and this creator has not weighed in yet. --}}
    <a href="{{ route('creator.questions.show', $question) }}" @click.stop class="{{ $classes }}">Add your answer →</a>
@endif
