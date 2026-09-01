@props(['question'])

{{-- Shown to the asker on their own question, and to admins. Never to the public:
     a hidden question is a 404 for everyone else. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-soil-300 bg-soil-50 px-4 py-3']) }}>
    <p class="font-body text-sm font-semibold text-soil-700">This question is hidden</p>
    <p class="mt-1 font-body text-sm text-soil-600">
        A moderator has taken it out of public view. You can still see it here, but it no longer
        appears in the question feed and cannot be responded to.
    </p>

    @if ($question->hidden_reason)
        <p class="mt-2 border-l-2 border-soil-300 pl-3 font-body text-sm italic text-soil-600">
            {{ $question->hidden_reason }}
        </p>
    @endif
</div>
