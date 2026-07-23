<?php

namespace App\Livewire;

use App\Enums\QuestionStatus;
use App\Jobs\NotifyAskerOfAnswer;
use App\Models\Question;
use App\Services\MarkdownRenderer;
use Livewire\Component;

class CreatorQuestionDetail extends Component
{
    public int $questionId;
    public string $answer = '';

    public function mount(Question $question): void
    {
        $this->questionId = $question->id;
    }

    public function render(MarkdownRenderer $markdown)
    {
        // Always fetch fresh from DB so status changes (claim/answer by others) are reflected
        $question = Question::with(['asker:id,name', 'claimer:id,name', 'answerer:id,name'])
            ->findOrFail($this->questionId);

        $renderedAnswer = $question->answer ? $markdown->render($question->answer) : null;

        return view('livewire.creator.question-detail', [
            'question'       => $question,
            'renderedAnswer' => $renderedAnswer,
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

    public function submitAnswer(): void
    {
        $validated = $this->validate([
            'answer' => ['required', 'string', 'between:10,10000'],
        ], [
            'answer.required' => 'Answer text is required.',
            'answer.between'  => 'Answer must be 10–10 000 characters.',
        ]);

        $updated = Question::where('id', $this->questionId)
            ->where('status', QuestionStatus::Claimed)
            ->where('claimed_by', auth()->id())
            ->update([
                'status'      => QuestionStatus::Answered,
                'answer'      => $validated['answer'],
                'answered_by' => auth()->id(),
                'answered_at' => now(),
            ]);

        if ($updated === 0) {
            $this->addError('answer', 'Could not submit — question may no longer be claimed by you.');
            return;
        }

        NotifyAskerOfAnswer::dispatch(Question::find($this->questionId));

        $this->redirect(route('creator.questions.show', $this->questionId));
    }
}