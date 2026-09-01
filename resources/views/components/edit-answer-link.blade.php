@props([
    'question',
    'answer',
])

{{-- The way back into the edit form from a page that has none of its own.
     Renders nothing for anyone who cannot open that form — which is almost
     everyone looking at a public question. --}}
@if ($answer && $answer->isEditFormOpenTo($user = auth()->user()))
    <a href="{{ route('creator.questions.show', $question) }}"
        class="shrink-0 font-body text-xs font-semibold text-leaf-600 hover:underline">
        {{ $answer->created_by === $user->id ? 'Edit your response' : 'Edit response' }} →
    </a>
@endif
