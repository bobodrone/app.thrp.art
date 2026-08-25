<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Livewire\Livewire;

class HomeController extends Controller
{
    public function __construct(protected MarkdownRenderer $markdown) {}

    public function index(Request $request): View
    {
        Livewire::forceAssetInjection();

        // role comes along because the answerer credit links to public profiles.
        // The bare `answers` load is what lets each card count the answers and
        // work out whether this viewer may add one, without a query per card.
        $questions = Question::with([
            'asker:id,name',
            'primaryAnswer.author:id,name,role',
            'answers:id,question_id,created_by',
        ])
            // Hidden questions leave the feed entirely; the asker finds theirs
            // on /my-questions, where the reason is shown.
            ->visible()
            ->select(['id', 'content', 'status', 'asked_by', 'claimed_by', 'primary_answer_id', 'created_at'])
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Question $q) => [
                'question'        => $q,
                'renderedAnswer' => $q->hasVisibleAnswer() ? $this->markdown->render($q->primaryAnswer->body) : null,
            ]);

        return view('home', [
            'questions'   => $questions,
            'user'        => $request->user(),
        ]);
    }
}
