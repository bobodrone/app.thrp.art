<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MarkdownRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PublicCreatorController extends Controller
{
    public function show(User $user, MarkdownRenderer $markdown): View
    {
        // Members have no public profile — don't confirm the id exists either.
        // Neither does a blocked account, which is gone from the directory too.
        abort_unless($user->isCreator() && ! $user->isBlocked(), 404);

        $user->loadCount([
            'answers as answers_count' => fn (Builder $q) => $q->publiclyCredited(),
        ]);

        return view('creators.show', [
            'creator'     => $user,
            'renderedBio' => $user->bio ? $markdown->renderBio($user->bio) : null,
        ]);
    }
}
