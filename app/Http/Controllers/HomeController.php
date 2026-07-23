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

        $questions = Question::with(['asker:id,name', 'answerer:id,name'])
            ->select(['id', 'content', 'status', 'answer', 'asked_by', 'answered_by', 'created_at', 'answered_at', 'answer_deleted_at'])
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Question $q) => [
                'question'        => $q,
                'renderedAnswer' => $q->hasVisibleAnswer() ? $this->markdown->render($q->answer) : null,
            ]);

        return view('home', [
            'questions'   => $questions,
            'user'        => $request->user(),
        ]);
    }
}