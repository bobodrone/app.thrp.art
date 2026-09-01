<div class="mx-auto max-w-3xl px-4 py-10">
    <a href="{{ route('creator.dashboard') }}" class="mb-6 inline-block font-body text-sm font-semibold text-leaf-600 hover:underline">← Responder Dashboard</a>

    @if (session('claim_error'))
        <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ session('claim_error') }}</p>
    @endif
    @error('claim')
        <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ $message }}</p>
    @enderror
    @error('unclaim')
        <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ $message }}</p>
    @enderror
    @error('answer')
        <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ $message }}</p>
    @enderror
    @error('promote')
        <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ $message }}</p>
    @enderror
    @error('moderate')
        <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ $message }}</p>
    @enderror

    @if (session('moderation-ok'))
        <p class="mb-4 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">{{ session('moderation-ok') }}</p>
    @endif

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
            <p class="mb-4 font-body text-sm text-soil-600">Claim this question to start working on the response.</p>
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
            <p class="mb-4 font-body text-sm font-medium text-soil-700">Write your response</p>

            <div class="space-y-4">
                <x-image-upload
                    wire-model="answerImage"
                    clear-action="clearAnswerImage"
                    :current-url="$newImagePreview"
                />
                <x-markdown-editor wire-model="answer" :initial="$answer" />
                <div class="flex justify-end">
                    <button type="button"
                        wire:click="submitAnswer"
                        class="rounded-xl bg-leaf-600 px-6 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500">
                        Submit Response
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
            This question is currently being responded to by another responder.
        </div>

    {{-- State D: responded --}}
    @elseif ($isAnswered && $renderedAnswer)
        <div class="mt-6 rounded-2xl border border-leaf-200 bg-white p-8 shadow-sm">
            @if ($editingAnswerId === $question->primary_answer_id)
                <div wire:key="edit-answer-{{ $question->primary_answer_id }}">
                    <x-answer-editor :draft="$answerDraft" :image-preview="$editImagePreview" />
                </div>
            @else
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <p class="font-body text-xs font-medium uppercase tracking-wide text-soil-400">Response</p>
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($canEditAnswer)
                            <button type="button" wire:click="startEditAnswer"
                                class="font-body text-sm font-semibold text-leaf-600 hover:underline">
                                Edit response
                            </button>
                        @endif
                        @if ($canModerate)
                            <button type="button" wire:click="removeAnswer({{ $question->primary_answer_id }})"
                                wire:confirm="Remove the main response? The question reopens for claiming, and the response can be restored from this page."
                                class="font-body text-sm font-semibold text-poppy-600 hover:underline">
                                Remove
                            </button>
                        @endif
                    </div>
                </div>
                <x-answer-body
                    :answer="$question->primaryAnswer"
                    :rendered="$renderedAnswer"
                    :viewer="auth()->user()"
                />
                @if ($question->primaryAnswer->anonymously)
                    <p class="mt-1 font-body text-xs text-soil-400">Posted anonymously</p>
                @endif
            @endif
        </div>
    @endif

    {{-- Alternative responses from other responders --}}
    @if ($otherAnswers->isNotEmpty())
        <h2 class="mb-3 mt-8 font-body text-xs font-semibold uppercase tracking-wider text-soil-400">
            @if ($question->hasVisibleAnswer())
                {{ $otherAnswers->count() }} {{ \Illuminate\Support\Str::plural('other response', $otherAnswers->count()) }}
            @else
                {{-- No main response to be "other" than — it was removed, or never claimed. --}}
                {{ $otherAnswers->count() }} {{ \Illuminate\Support\Str::plural('response', $otherAnswers->count()) }}
            @endif
        </h2>

        <div class="space-y-4">
            @foreach ($otherAnswers as $row)
                <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm"
                     wire:key="answer-{{ $row['answer']->id }}">
                    @if ($editingAnswerId === $row['answer']->id)
                        <x-answer-editor
                            :draft="$answerDraft"
                            :image-preview="$editImagePreview"
                            heading="Edit your response"
                        />
                    @else
                        @if ($row['canEdit'] || $row['canPromote'] || $canModerate)
                            <div class="mb-3 flex flex-wrap items-center justify-end gap-4">
                                @if ($row['canPromote'])
                                    <button type="button"
                                        wire:click="promoteAnswer({{ $row['answer']->id }})"
                                        wire:confirm="Make this the main response? The current one becomes an alternative."
                                        class="font-body text-sm font-semibold text-sun-700 hover:underline">
                                        Make main response
                                    </button>
                                @endif
                                @if ($row['canEdit'])
                                    <button type="button" wire:click="startEditAnswer({{ $row['answer']->id }})"
                                        class="font-body text-sm font-semibold text-leaf-600 hover:underline">
                                        Edit response
                                    </button>
                                @endif
                                @if ($canModerate)
                                    <button type="button" wire:click="removeAnswer({{ $row['answer']->id }})"
                                        wire:confirm="Remove this response? It can be restored from this page."
                                        class="font-body text-sm font-semibold text-poppy-600 hover:underline">
                                        Remove
                                    </button>
                                @endif
                            </div>
                        @endif
                        <x-answer-body
                            :answer="$row['answer']"
                            :rendered="$row['rendered']"
                            :viewer="auth()->user()"
                        />
                        @if ($row['answer']->anonymously)
                            <p class="mt-1 font-body text-xs text-soil-400">Posted anonymously</p>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Removed responses, admins only — nothing here is public --}}
    @if ($removedAnswers->isNotEmpty())
        <h2 class="mb-3 mt-8 font-body text-xs font-semibold uppercase tracking-wider text-soil-400">
            {{ $removedAnswers->count() }} removed {{ \Illuminate\Support\Str::plural('response', $removedAnswers->count()) }}
        </h2>

        <div class="space-y-4">
            @foreach ($removedAnswers as $row)
                <div class="rounded-2xl border border-dashed border-soil-300 bg-soil-50 p-6"
                     wire:key="removed-{{ $row['answer']->id }}">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-4">
                        <span class="rounded-full bg-soil-100 px-2 py-0.5 font-body text-xs text-soil-500">
                            Hidden from everyone
                        </span>
                        <button type="button" wire:click="restoreAnswer({{ $row['answer']->id }})"
                            class="font-body text-sm font-semibold text-leaf-600 hover:underline">
                            Restore
                        </button>
                    </div>
                    <div class="opacity-60">
                        <x-answer-body
                            :answer="$row['answer']"
                            :rendered="$row['rendered']"
                            :viewer="auth()->user()"
                        />
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Any responder without a response here may add one alongside the main one --}}
    @if ($canAddAlternative)
        <div class="mt-8 rounded-2xl border border-leaf-200 bg-white p-8 shadow-sm">
            <p class="mb-1 font-body text-sm font-medium text-soil-700">Add your response</p>
            <p class="mb-4 font-body text-xs text-soil-400">
                This question already has a response. Yours will be shown alongside it.
            </p>

            @error('alternative')
                <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">{{ $message }}</p>
            @enderror

            <div class="space-y-4">
                <x-image-upload
                    wire-model="alternativeImage"
                    clear-action="clearAlternativeImage"
                    :current-url="$alternativeImagePreview"
                />
                <x-markdown-editor wire-model="alternative" :initial="$alternative" />
                <div class="flex justify-end">
                    <button type="button"
                        wire:click="submitAlternative"
                        class="rounded-xl bg-leaf-600 px-6 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500">
                        Post my response
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>