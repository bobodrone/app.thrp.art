<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyQuestionsController extends Controller
{
    public function index(Request $request): View
    {
        $questions = Question::where('asked_by', $request->user()->id)
            ->latest('created_at')
            ->get();

        return view('my-questions', ['questions' => $questions]);
    }
}
