<?php

namespace App\Livewire;

use App\Enums\QuestionStatus;
use App\Jobs\NotifyAskerOfAnswer;
use App\Livewire\Concerns\HandlesImageUploads;
use App\Models\Answer;
use App\Models\Question;
use App\Services\MarkdownRenderer;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CreatorQuestionDetail extends Component
{
    use HandlesImageUploads, WithFileUploads;

    protected function uploadConfigKey(): string
    {
        return 'answer_image';
    }

    public int $questionId;
    public string $answer = '';

    /** Pending image for a brand-new answer. */
    public ?TemporaryUploadedFile $answerImage = null;

    /** An alternative answer alongside someone else's main one. */
    public string $alternative = '';

    public ?TemporaryUploadedFile $alternativeImage = null;

    /**
     * Which answer the edit form is open on — the main one or one of the
     * alternatives. Locked because it decides what gets overwritten; the
     * ownership check in answerBeingEdited() is the real guard.
     */
    #[Locked]
    public ?int $editingAnswerId = null;

    public string $answerDraft = '';

    /** Pending replacement image while editing an existing answer. */
    public ?TemporaryUploadedFile $answerImageDraft = null;

    /** Set when the creator clears the already-saved image while editing. */
    public bool $removeAnswerImage = false;

    public function mount(Question $question): void
    {
        $this->questionId = $question->id;
    }

    public function render(MarkdownRenderer $markdown)
    {
        // Always fetch fresh from DB so status changes (claim/answer by others) are reflected
        $question = Question::with([
            'asker:id,name',
            'claimer:id,name',
            'primaryAnswer.author:id,name,role',
            'answers.author:id,name,role',
        ])->findOrFail($this->questionId);

        $viewer = auth()->user();

        $renderedAnswer = $question->hasVisibleAnswer()
            ? $markdown->render($question->primaryAnswer->body)
            : null;

        // The image under the edit form belongs to whichever answer is open,
        // not necessarily the main one.
        $editing = $question->answers->firstWhere('id', $this->editingAnswerId);

        return view('livewire.creator.question-detail', [
            'question'       => $question,
            'renderedAnswer' => $renderedAnswer,
            'canEditAnswer'  => $question->isAnswerEditableBy($viewer),
            'canAddAlternative' => $question->isAnswerableBy($viewer),
            'canModerate'    => $canModerate = $question->isModeratableBy($viewer),
            'otherAnswers'   => $question->otherAnswers()->map(fn (Answer $answer) => [
                'answer'     => $answer,
                'rendered'   => $markdown->render($answer->body),
                'canEdit'    => $answer->isEditableBy($viewer),
                'canPromote' => $question->isPromotableBy($viewer, $answer),
            ]),
            // Only moderators are shown what has been taken down.
            'removedAnswers' => $canModerate
                ? $question->answers()->onlyTrashed()->with('author:id,name,role')->get()
                    ->map(fn (Answer $answer) => [
                        'answer'   => $answer,
                        'rendered' => $markdown->render($answer->body),
                    ])
                : collect(),
            'newImagePreview'  => $this->previewUrl($this->answerImage),
            'alternativeImagePreview' => $this->previewUrl($this->alternativeImage),
            'editImagePreview' => $this->previewUrl(
                $this->answerImageDraft,
                $this->removeAnswerImage ? null : $editing?->imageUrl(),
            ),
        ])
        ->layout('layouts.app')
        ->title('Question — Responder View — THRP');
    }

    public function claim()
    {
        $claimed = Question::findOrFail($this->questionId)->claimBy(auth()->user());

        if (! $claimed) {
            $this->addError('claim', 'Question has already been claimed by someone else.');
            return;
        }

        $this->redirect(route('creator.questions.show', $this->questionId));
    }

    public function unclaim(): void
    {
        $updated = Question::where('id', $this->questionId)
            ->where('status', QuestionStatus::Claimed)
            ->where('claimed_by', auth()->id())
            ->update([
                'status'      => QuestionStatus::Asked,
                'claimed_by'  => null,
                'claimed_at'  => null,
            ]);

        if ($updated === 0) {
            $this->addError('unclaim', 'Could not unclaim — you may not be the current claimer.');
            return;
        }

        $this->redirect(route('creator.dashboard'));
    }

    /** Validate as soon as a file is picked, so mistakes surface immediately. */
    public function updatedAnswerImage(): void
    {
        $this->validateOnly('answerImage', $this->imageRules('answerImage'), $this->imageMessages('answerImage'));
    }

    public function updatedAnswerImageDraft(): void
    {
        // A fresh pick supersedes an earlier "remove".
        $this->removeAnswerImage = false;

        $this->validateOnly('answerImageDraft', $this->imageRules('answerImageDraft'), $this->imageMessages('answerImageDraft'));
    }

    public function updatedAlternativeImage(): void
    {
        $this->validateOnly('alternativeImage', $this->imageRules('alternativeImage'), $this->imageMessages('alternativeImage'));
    }

    public function clearAnswerImage(): void
    {
        $this->reset('answerImage');
        $this->resetErrorBag('answerImage');
    }

    public function clearAlternativeImage(): void
    {
        $this->reset('alternativeImage');
        $this->resetErrorBag('alternativeImage');
    }

    /**
     * Clearing while editing drops the pending upload *and* marks the saved
     * image for removal — one button covers both, since the form only ever
     * shows one image at a time.
     */
    public function clearAnswerImageDraft(): void
    {
        $this->reset('answerImageDraft');
        $this->resetErrorBag('answerImageDraft');
        $this->removeAnswerImage = true;
    }

    public function submitAnswer(): void
    {
        $validated = $this->validate([
            'answer' => ['required', 'string', 'between:10,10000'],
        ] + $this->imageRules('answerImage'), [
            'answer.required' => 'Answer text is required.',
            'answer.between'  => 'Answer must be 10–10 000 characters.',
        ] + $this->imageMessages('answerImage'));

        $imagePath = $this->answerImage ? $this->storeImage($this->answerImage) : null;

        $question = Question::findOrFail($this->questionId);

        // A reopened question may still carry the image of this creator's
        // removed answer, whose row is about to be reused.
        $previousPath = $question->answers()->withTrashed()
            ->writtenBy(auth()->id())
            ->value('image_path');

        $answer = $question->publishPrimaryAnswerFrom(auth()->user(), $validated['answer'], $imagePath);

        if ($answer === null) {
            $this->deleteImage($imagePath);
            $this->addError('answer', 'Could not submit — question may no longer be claimed by you.');
            return;
        }

        $this->deleteImage($previousPath);

        NotifyAskerOfAnswer::dispatch($question);

        $this->redirect(route('creator.questions.show', $this->questionId));
    }

    /**
     * Publish an alternative alongside the main answer. No claim is involved —
     * the model decides whether this creator is allowed one.
     */
    public function submitAlternative(): void
    {
        $validated = $this->validate([
            'alternative' => ['required', 'string', 'between:10,10000'],
        ] + $this->imageRules('alternativeImage'), [
            'alternative.required' => 'Answer text is required.',
            'alternative.between'  => 'Answer must be 10–10 000 characters.',
        ] + $this->imageMessages('alternativeImage'));

        $imagePath = $this->alternativeImage ? $this->storeImage($this->alternativeImage) : null;

        $question = Question::findOrFail($this->questionId);

        // An answer of theirs that an admin removed still holds their slot, and
        // adding a new one reuses that row — so its image needs cleaning up.
        $previousPath = $question->answers()->withTrashed()
            ->writtenBy(auth()->id())
            ->value('image_path');

        $answer = $question->addAlternativeAnswerFrom(auth()->user(), $validated['alternative'], $imagePath);

        if ($answer === null) {
            $this->deleteImage($imagePath);
            $this->addError('alternative', 'Could not add your answer — you may already have one on this question.');
            return;
        }

        $this->deleteImage($previousPath);

        NotifyAskerOfAnswer::dispatch($question);

        $this->redirect(route('creator.questions.show', $this->questionId));
    }

    /**
     * Moderation: hide an answer, main or alternative. Removing the main one
     * reopens the question; the row survives either way and can be restored
     * from the same page.
     */
    public function removeAnswer(int $answerId): void
    {
        $question = Question::with('answers')->findOrFail($this->questionId);
        $answer   = $question->answers->firstWhere('id', $answerId);

        if ($answer === null || ! $question->isModeratableBy(auth()->user())) {
            $this->addError('moderate', 'You are not allowed to remove this answer.');
            return;
        }

        $wasPrimary = $question->primary_answer_id === $answer->id;

        $question->removeAnswer($answer);

        session()->flash('moderation-ok', $wasPrimary
            ? 'Main answer removed — the question is open for claiming again.'
            : 'Answer removed.');

        $this->redirect(route('creator.questions.show', $this->questionId));
    }

    /**
     * Moderation: put a hidden answer back. If the main slot was refilled while
     * it was down, it returns as an alternative.
     */
    public function restoreAnswer(int $answerId): void
    {
        $question = Question::findOrFail($this->questionId);
        $answer   = $question->answers()->onlyTrashed()->find($answerId);

        if ($answer === null || ! $question->isModeratableBy(auth()->user())) {
            $this->addError('moderate', 'You are not allowed to restore this answer.');
            return;
        }

        $question->restoreAnswer($answer);

        session()->flash('moderation-ok', 'Answer restored.');

        $this->redirect(route('creator.questions.show', $this->questionId));
    }

    /**
     * Moderation: move an alternative into the main slot. The answer that was
     * there becomes an alternative rather than disappearing, so nothing is lost
     * if an admin promotes the wrong one.
     */
    public function promoteAnswer(int $answerId): void
    {
        $question = Question::with('answers')->findOrFail($this->questionId);

        // Reading from the loaded relation keeps hidden answers out of reach:
        // promoting one would put a soft-deleted answer in the main slot.
        $answer = $question->answers->firstWhere('id', $answerId);

        if ($answer === null || ! $question->isPromotableBy(auth()->user(), $answer)) {
            $this->addError('promote', 'You are not allowed to change the main answer.');
            return;
        }

        $question->promoteToPrimary($answer);

        session()->flash('moderation-ok', 'Main answer updated.');

        $this->redirect(route('creator.questions.show', $this->questionId));
    }

    /**
     * Open the edit form on one answer, defaulting to the main one.
     */
    public function startEditAnswer(?int $answerId = null): void
    {
        $question = Question::with('answers')->findOrFail($this->questionId);

        $answer = $answerId === null
            ? $question->primaryAnswer
            : $question->answers->firstWhere('id', $answerId);

        if ($answer === null || ! $answer->isEditableBy(auth()->user())) {
            return;
        }

        $this->answerDraft     = $answer->body;
        $this->editingAnswerId = $answer->id;
        $this->reset('answerImageDraft', 'removeAnswerImage');
        $this->resetErrorBag();
    }

    public function cancelEditAnswer(): void
    {
        $this->reset('editingAnswerId', 'answerDraft', 'answerImageDraft', 'removeAnswerImage');
        $this->resetErrorBag();
    }

    /**
     * The answer the edit form is open on, or null when there is none this
     * viewer may write to. Re-checked on every save rather than trusted from
     * the request.
     */
    protected function answerBeingEdited(): ?Answer
    {
        if ($this->editingAnswerId === null) {
            return null;
        }

        $answer = Answer::find($this->editingAnswerId);

        if ($answer === null || $answer->question_id !== $this->questionId) {
            return null;
        }

        return $answer->isEditableBy(auth()->user()) ? $answer : null;
    }

    public function updateAnswer(): void
    {
        $answer = $this->answerBeingEdited();

        if ($answer === null) {
            $this->addError('answerDraft', 'You are not allowed to edit this answer.');
            return;
        }

        $validated = $this->validate([
            'answerDraft' => ['required', 'string', 'between:10,10000'],
        ] + $this->imageRules('answerImageDraft'), [
            'answerDraft.required' => 'Answer text is required.',
            'answerDraft.between'  => 'Answer must be 10–10 000 characters.',
        ] + $this->imageMessages('answerImageDraft'));

        $previousPath = $answer->image_path;
        $imagePath    = match (true) {
            (bool) $this->answerImageDraft => $this->storeImage($this->answerImageDraft),
            $this->removeAnswerImage       => null,
            default                        => $previousPath,
        };

        // Edit in place — no re-notification to the asker, and published_at
        // stays put so the answer keeps its original timestamp.
        $answer->update([
            'body'       => $validated['answerDraft'],
            'image_path' => $imagePath,
        ]);

        if ($previousPath !== $imagePath) {
            $this->deleteImage($previousPath);
        }

        $this->reset('editingAnswerId', 'answerDraft', 'answerImageDraft', 'removeAnswerImage');
    }
}