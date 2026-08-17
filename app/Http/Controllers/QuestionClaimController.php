<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class QuestionClaimController extends Controller
{
    /**
     * Claim from anywhere a question is shown (home feed, question page) and
     * land on the responder view, where the answer gets written.
     */
    public function store(Request $request, Question $question): RedirectResponse
    {
        if (! $question->claimBy($request->user())) {
            // Lost the race — the responder view explains who holds it now.
            return Redirect::route('creator.questions.show', $question)
                ->with('claim_error', 'Question has already been claimed by someone else.');
        }

        return Redirect::route('creator.questions.show', $question);
    }
}
