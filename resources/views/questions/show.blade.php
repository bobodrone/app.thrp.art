<x-app-layout>
    <x-slot name="title">Question — THRP</x-slot>

    <div class="px-4 py-12">
        <div class="mx-auto max-w-2xl">

            <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-1.5 font-body text-sm font-medium text-leaf-600 hover:text-leaf-700">
                ← Back to questions
            </a>

            <article class="rounded-2xl bg-white p-8 shadow-lg">
                <div class="mb-6 flex items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :status="$question->status" />
                        @if ($question->isHidden())
                            <x-hidden-badge />
                        @endif
                    </div>
                    <span class="shrink-0 font-body text-xs text-soil-400">{{ $question->created_at->diffForHumans() }}</span>
                </div>

                <h1 class="font-display mb-3 text-2xl font-bold leading-snug text-soil-900">
                    {{ $question->content }}
                </h1>

                @if ($question->asker)
                    <p class="font-body text-xs text-soil-400">
                        Asked by <span class="font-medium text-soil-600">{{ $question->asker->name }}</span>
                    </p>
                @endif

                @if ($question->isHidden())
                    <x-hidden-notice :question="$question" class="mt-6" />
                @elseif ($question->isClaimableBy(auth()->user()))
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3">
                        <p class="font-body text-sm text-leaf-700">No one has responded to this yet.</p>
                        <x-question-action :question="$question" size="md" />
                    </div>
                @elseif ($question->status === \App\Enums\QuestionStatus::Claimed && $question->claimer)
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-sky-100 px-4 py-3">
                        <p class="font-body text-sm text-sky-600">
                            @if ($question->isAwaitingAnswerFrom(auth()->user()))
                                You claimed this question — pick up where you left off.
                            @else
                                Being responded to by <strong>{{ $question->claimerNameFor(auth()->user()) }}</strong>…
                            @endif
                        </p>
                        {{-- Only the claimer gets a link back; for everyone else this renders nothing. --}}
                        <x-question-action :question="$question" size="md" />
                    </div>
                @endif

                @if ($question->status === \App\Enums\QuestionStatus::Answered && $renderedAnswer)
                    <div class="mt-8 border-t-2 border-sun-300 pt-8">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <x-flower size="20" petalColor="#FFD600" centerColor="#1A5C38" dotColor="#FFD600" />
                                <h2 class="font-body text-xs font-semibold uppercase tracking-wider text-leaf-600">Response</h2>
                            </div>
                            <x-edit-answer-link :question="$question" :answer="$question->primaryAnswer" />
                        </div>
                        <x-answer-body
                            :answer="$question->primaryAnswer"
                            :rendered="$renderedAnswer"
                            :viewer="auth()->user()"
                        />
                    </div>
                @endif
            </article>

            {{-- Alternative answers from other responders, oldest first. --}}
            @if ($otherAnswers->isNotEmpty())
                <section class="mt-8">
                    <h2 class="mb-4 font-body text-xs font-semibold uppercase tracking-wider text-soil-400">
                        {{ $otherAnswers->count() }} {{ \Illuminate\Support\Str::plural('other response', $otherAnswers->count()) }} from the community
                    </h2>

                    <div class="space-y-4">
                        @foreach ($otherAnswers as $row)
                            <article class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
                                @if ($row['answer']->isEditFormOpenTo(auth()->user()))
                                    <div class="mb-3 flex justify-end">
                                        <x-edit-answer-link :question="$question" :answer="$row['answer']" />
                                    </div>
                                @endif
                                <x-answer-body
                                    :answer="$row['answer']"
                                    :rendered="$row['rendered']"
                                    :viewer="auth()->user()"
                                />
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Responders who have not answered yet get a way in. --}}
            @if ($question->isAnswerableBy(auth()->user()))
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3">
                    <p class="font-body text-sm text-leaf-700">Have a different take on this one?</p>
                    <x-question-action :question="$question" size="md" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>