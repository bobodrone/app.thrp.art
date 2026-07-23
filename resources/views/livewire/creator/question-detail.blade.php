<div class="mx-auto max-w-3xl px-4 py-10">
    <a href="{{ route('creator.dashboard') }}" class="mb-6 inline-block font-body text-sm font-semibold text-leaf-600 hover:underline">← Creator Dashboard</a>

    @error('claim')
        <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ $message }}</p>
    @enderror
    @error('unclaim')
        <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ $message }}</p>
    @enderror
    @error('answer')
        <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ $message }}</p>
    @enderror

    <div class="mb-6 flex items-center justify-between">
        <x-status-badge :status="$question->status" />
        <span class="font-body text-xs text-soil-400">{{ $question->created_at->diffForHumans() }}</span>
    </div>

    <div class="rounded-2xl border border-leaf-200 bg-white p-8 shadow-sm">
        <p class="mb-2 font-body text-xs font-medium uppercase tracking-wide text-soil-400">Question</p>
        <p class="mb-4 font-body text-base text-soil-900">{{ $question->content }}</p>
        @if ($question->asker)
            <p class="font-body text-xs text-soil-400">Asked by {{ $question->asker->name }}</p>
        @endif
    </div>

    @php
        $isMyQuestion = $question->status === \App\Enums\QuestionStatus::Claimed
            && $question->claimed_by === auth()->id();
        $canClaim     = $question->status === \App\Enums\QuestionStatus::Asked;
        $isAnswered   = $question->status === \App\Enums\QuestionStatus::Answered;
        $claimedByOther = $question->status === \App\Enums\QuestionStatus::Claimed && ! $isMyQuestion;
    @endphp

    {{-- State A: open, can claim --}}
    @if ($canClaim)
        <div class="mt-6 rounded-2xl border border-leaf-200 bg-white p-6 text-center shadow-sm">
            <p class="mb-4 font-body text-sm text-soil-600">Claim this question to start working on the answer.</p>
            <form wire:submit="claim">
                @csrf
                <button type="submit"
                    class="rounded-xl bg-leaf-600 px-6 py-2.5 font-body text-sm font-semibold text-white hover:bg-leaf-500">
                    Claim this question
                </button>
            </form>
        </div>

    {{-- State B: claimed by me — show editor --}}
    @elseif ($isMyQuestion)
        <div class="mt-6 rounded-2xl border border-leaf-200 bg-white p-8 shadow-sm">
            <p class="mb-4 font-body text-sm font-medium text-soil-700">Write your answer</p>

            <div class="space-y-4">
                <x-markdown-editor wire-model="answer" :initial="$answer" />
                <div class="flex justify-end">
                    <button type="button"
                        wire:click="submitAnswer"
                        class="rounded-xl bg-leaf-600 px-6 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500">
                        Submit Answer
                    </button>
                </div>
            </div>

            <div class="mt-3 border-t border-leaf-200 pt-3">
                <button type="button"
                    wire:click="unclaim"
                    class="font-body text-sm text-soil-400 hover:text-soil-600 hover:underline">
                    Changed your mind? Unclaim this question
                </button>
            </div>
        </div>

    {{-- State C: claimed by someone else --}}
    @elseif ($claimedByOther)
        <div class="mt-6 rounded-xl bg-sky-100 px-5 py-4 font-body text-sm text-sky-600">
            This question is currently being answered by another creator.
        </div>

    {{-- State D: answered --}}
    @elseif ($isAnswered && $renderedAnswer)
        <div class="mt-6 rounded-2xl border border-leaf-200 bg-white p-8 shadow-sm">
            <p class="mb-4 font-body text-xs font-medium uppercase tracking-wide text-soil-400">Answer</p>
            <div class="prose prose-sm max-w-none font-body prose-headings:font-display prose-a:text-leaf-600">
                {!! $renderedAnswer !!}
            </div>
            @if ($question->answerer && $question->answered_at)
                <p class="mt-6 font-body text-xs text-soil-400">
                    Answered by {{ $question->answerer->name }} · {{ $question->answered_at->diffForHumans() }}
                </p>
            @endif
        </div>
    @endif
</div>