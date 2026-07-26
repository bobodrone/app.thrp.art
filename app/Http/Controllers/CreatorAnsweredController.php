<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreatorAnsweredController extends Controller
{
    public function index(Request $request): View
    {
        $user    = $request->user();
        $isAdmin = $user->role === UserRole::Admin;

        // Admins moderate every answer, so they see the whole history;
        // a creator only ever sees the questions they answered themselves —
        // as the main answer or as an alternative — ordered by their own
        // answer's date rather than whatever landed on the question last.
        $answered = Question::with(['asker:id,name', 'primaryAnswer.author:id,name,role'])
            ->when($isAdmin, fn ($q) => $q->answered(), fn ($q) => $q->answeredBy($user->id))
            ->withMax(
                ['answers as last_answered_at' => fn (Builder $a) => $isAdmin ? $a : $a->writtenBy($user->id)],
                'published_at',
            )
            ->orderByDesc('last_answered_at')
            ->get();

        return view('creator.answered', [
            'answered' => $answered,
            'isAdmin'  => $isAdmin,
        ]);
    }
}
