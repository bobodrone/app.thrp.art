<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreatorAnsweredController extends Controller
{
    public function index(Request $request): View
    {
        $user    = $request->user();
        $isAdmin = $user->role === UserRole::Admin;

        // Admins moderate every answer, so they see the whole history;
        // a creator only ever sees the ones they wrote themselves.
        $answered = Question::with(['asker:id,name', 'answerer:id,name'])
            ->when($isAdmin, fn ($q) => $q->answered(), fn ($q) => $q->answeredBy($user->id))
            ->latest('answered_at')
            ->get();

        return view('creator.answered', [
            'answered' => $answered,
            'isAdmin'  => $isAdmin,
        ]);
    }
}
