<?php

namespace App\Livewire;

use App\Enums\QuestionStatus;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\On;
use Livewire\Component;

class CreatorDashboard extends Component
{
    public function render()
    {
        $userId = auth()->id();

        $openQuestions = Question::with('asker:id,name')
            ->open()
            ->oldest('created_at')
            ->get();

        $myClaimed = Question::with('asker:id,name')
            ->claimedBy($userId)
            ->oldest('claimed_at')
            ->get();

        return view('livewire.creator.dashboard', [
            'openQuestions' => $openQuestions,
            'myClaimed'     => $myClaimed,
        ])
        ->layout('layouts.app')
        ->title('Responder Dashboard — THRP');
    }

    public function claim($questionId): ?RedirectResponse
    {
        $claimed = Question::findOrFail($questionId)->claimBy(auth()->user());

        if (! $claimed) {
            $this->addError('claim_' . $questionId, 'Question has already been claimed by someone else.');
            return null;
        }

        return $this->redirect(route('creator.questions.show', $questionId));
    }

    public function unclaim($questionId): ?RedirectResponse
    {
        Question::where('id', $questionId)
            ->where('status', QuestionStatus::Claimed)
            ->where('claimed_by', auth()->id())
            ->update([
                'status'      => QuestionStatus::Asked,
                'claimed_by'  => null,
                'claimed_at'  => null,
            ]);

        return null;
    }
}