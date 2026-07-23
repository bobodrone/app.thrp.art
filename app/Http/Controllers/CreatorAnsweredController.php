<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class CreatorAnsweredController extends Controller
{
    public function index(Request $request): View
    {
        $answered = \App\Models\Question::with('asker:id,name')
            ->answeredBy($request->user()->id)
            ->latest('answered_at')
            ->get();

        return view('creator.answered', ['answered' => $answered]);
    }
}