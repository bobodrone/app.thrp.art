<?php

namespace App\Livewire;

use App\Enums\QuestionStatus;
use App\Jobs\NotifyAskerOfAnswer;
use App\Models\Question;
use App\Services\MarkdownRenderer;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CreatorQuestionDetail extends Component
{
    use WithFileUploads;

    public int $questionId;
    public string $answer = '';

    /** Pending image for a brand-new answer. */
    public ?TemporaryUploadedFile $answerImage = null;

    public bool $editingAnswer = false;
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
        $question = Question::with(['asker:id,name', 'claimer:id,name', 'answerer:id,name'])
            ->findOrFail($this->questionId);

        $renderedAnswer = $question->hasVisibleAnswer() ? $markdown->render($question->answer) : null;

        return view('livewire.creator.question-detail', [
            'question'       => $question,
            'renderedAnswer' => $renderedAnswer,
            'canEditAnswer'  => $question->isAnswerEditableBy(auth()->user()),
            'newImagePreview'  => $this->previewUrl($this->answerImage),
            'editImagePreview' => $this->previewUrl(
                $this->answerImageDraft,
                $this->removeAnswerImage ? null : $question->answerImageUrl(),
            ),
        ])
        ->layout('layouts.app')
        ->title('Question — Creator View — THRP');
    }

    public function claim()
    {
        $updated = Question::where('id', $this->questionId)
            ->where('status', QuestionStatus::Asked)
            ->update([
                'status'      => QuestionStatus::Claimed,
                'claimed_by'  => auth()->id(),
                'claimed_at'  => now(),
            ]);

        if ($updated === 0) {
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

    /**
     * What the upload widget should display: the staged upload if there is one,
     * otherwise the fallback (usually the already-saved image).
     *
     * Livewire cannot build a preview URL for every format it accepts — HEIC has
     * no entry in livewire.temporary_file_upload.preview_mimes — so a failure
     * here just means "no server-side preview", not a broken upload.
     */
    protected function previewUrl(?TemporaryUploadedFile $file, ?string $fallback = null): ?string
    {
        if ($file === null) {
            return $fallback;
        }

        try {
            return $file->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Validation rules for an uploaded image — all limits come from
     * config/uploads.php, which reads them from .env.
     */
    protected function imageRules(string $field): array
    {
        $config = config('uploads.answer_image');

        return [$field => [
            'nullable',
            'file',
            'extensions:'.implode(',', $config['extensions']),
            'mimetypes:'.implode(',', $config['mime_types']),
            'max:'.$config['max_kb'],
        ]];
    }

    protected function imageMessages(string $field): array
    {
        $config = config('uploads.answer_image');

        return [
            "{$field}.extensions" => 'Image must be a '.implode(', ', $config['extensions']).' file.',
            "{$field}.mimetypes"  => 'That file is not a valid image.',
            "{$field}.max"        => 'Image must be smaller than '.round($config['max_kb'] / 1024, 1).' MB.',
            "{$field}.uploaded"   => 'Upload failed — the file may be larger than the server allows.',
        ];
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

    public function clearAnswerImage(): void
    {
        $this->reset('answerImage');
        $this->resetErrorBag('answerImage');
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

    protected function storeImage(TemporaryUploadedFile $file): string
    {
        // store() picks a random filename — never trust the client's.
        return $file->store(
            config('uploads.answer_image.directory'),
            config('uploads.answer_image.disk'),
        );
    }

    protected function deleteImage(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(config('uploads.answer_image.disk'))->delete($path);
        }
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

        // A reopened question may still carry the image of its removed answer.
        $previousPath = Question::whereKey($this->questionId)->value('answer_image_path');

        $updated = Question::where('id', $this->questionId)
            ->where('status', QuestionStatus::Claimed)
            ->where('claimed_by', auth()->id())
            ->update([
                'status'            => QuestionStatus::Answered,
                'answer'            => $validated['answer'],
                'answer_image_path' => $imagePath,
                'answered_by'       => auth()->id(),
                'answered_at'       => now(),
                'answer_deleted_at' => null,
            ]);

        if ($updated === 0) {
            $this->deleteImage($imagePath);
            $this->addError('answer', 'Could not submit — question may no longer be claimed by you.');
            return;
        }

        $this->deleteImage($previousPath);

        NotifyAskerOfAnswer::dispatch(Question::find($this->questionId));

        $this->redirect(route('creator.questions.show', $this->questionId));
    }

    public function startEditAnswer(): void
    {
        $question = Question::findOrFail($this->questionId);

        if (! $question->isAnswerEditableBy(auth()->user())) {
            return;
        }

        $this->answerDraft   = $question->answer ?? '';
        $this->editingAnswer = true;
        $this->reset('answerImageDraft', 'removeAnswerImage');
        $this->resetErrorBag();
    }

    public function cancelEditAnswer(): void
    {
        $this->editingAnswer = false;
        $this->reset('answerDraft', 'answerImageDraft', 'removeAnswerImage');
        $this->resetErrorBag();
    }

    public function updateAnswer(): void
    {
        $question = Question::findOrFail($this->questionId);

        if (! $question->isAnswerEditableBy(auth()->user())) {
            $this->addError('answerDraft', 'You are not allowed to edit this answer.');
            return;
        }

        $validated = $this->validate([
            'answerDraft' => ['required', 'string', 'between:10,10000'],
        ] + $this->imageRules('answerImageDraft'), [
            'answerDraft.required' => 'Answer text is required.',
            'answerDraft.between'  => 'Answer must be 10–10 000 characters.',
        ] + $this->imageMessages('answerImageDraft'));

        $previousPath = $question->answer_image_path;
        $imagePath    = match (true) {
            (bool) $this->answerImageDraft => $this->storeImage($this->answerImageDraft),
            $this->removeAnswerImage       => null,
            default                        => $previousPath,
        };

        // Edit in place — no re-notification to the asker.
        Question::whereKey($this->questionId)->update([
            'answer'            => $validated['answerDraft'],
            'answer_image_path' => $imagePath,
        ]);

        if ($previousPath !== $imagePath) {
            $this->deleteImage($previousPath);
        }

        $this->editingAnswer = false;
        $this->reset('answerDraft', 'answerImageDraft', 'removeAnswerImage');
    }
}