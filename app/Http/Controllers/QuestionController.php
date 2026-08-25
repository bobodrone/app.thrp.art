<?php

namespace App\Http\Controllers;

use App\Enums\QuestionStatus;
use App\Http\Requests\StoreQuestionRequest;
use App\Jobs\NotifyCreatorsOfNewQuestion;
use App\Models\Answer;
use App\Models\Question;
use App\Services\MarkdownRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function __construct(protected MarkdownRenderer $markdown) {}

    public function show(Request $request, Question $question): View
    {
        // A hidden question is a 404 to the public. The asker still gets their
        // own — that page is where the moderator's reason is read — and admins
        // see everything.
        abort_unless($question->isViewableBy($request->user()), 404);

        $question->load([
            'asker:id,name',
            // posts_anonymously decides how the claimer is credited while they write.
            'claimer:id,name,posts_anonymously',
            'primaryAnswer.author:id,name,role',
            // role comes along because each credit links to a public profile.
            'answers.author:id,name,role',
            'hiddenBy:id,name',
        ]);

        return view('questions.show', [
            'question'        => $question,
            'renderedAnswer' => $question->hasVisibleAnswer() ? $this->markdown->render($question->primaryAnswer->body) : null,
            'otherAnswers'   => $question->otherAnswers()->map(fn (Answer $answer) => [
                'answer'   => $answer,
                'rendered' => $this->markdown->render($answer->body),
            ]),
        ]);
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        $q = Question::create([
            'content'  => $request->validated('content'),
            'status'   => QuestionStatus::Asked,
            'asked_by' => $request->user()->id,
        ]);

        NotifyCreatorsOfNewQuestion::dispatch($q);

        return Redirect::route('questions.show', $q)->with('status', 'question-asked');
    }
}
