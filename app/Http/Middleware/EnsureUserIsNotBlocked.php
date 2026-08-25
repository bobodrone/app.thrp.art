<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a block into something that takes effect now rather than at the
 * blocked person's next voluntary logout.
 *
 * Refusing them at sign-in is not enough on its own: whoever is being blocked
 * is, by definition, usually mid-session, and a remember-me cookie would let
 * them back in afterwards. This runs on every request in the web group, so the
 * session dies on their very next click.
 */
class EnsureUserIsNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isBlocked()) {
            return $next($request);
        }

        // Read before the logout, while the model is still to hand.
        $notice = $user->blockNotice();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $notice]);
    }
}
