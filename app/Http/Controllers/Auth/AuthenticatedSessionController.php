<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        // Read ?next= for safe post-login redirect (matches SvelteKit behaviour)
        $next = $request->query('next', '/');
        $next = $this->safeRedirectPath($next);

        // ?reset=success URL param lets the reset-password flow flag the success banner
        if ($request->query('reset') === 'success') {
            session()->flash('status', 'password-reset');
        }

        return view('auth.login', ['next' => $next]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $next = $this->safeRedirectPath($request->input('next', '/'));

        return redirect()->to($next);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Only allow same-origin relative redirects — never protocol-relative or absolute URLs.
     */
    private function safeRedirectPath(?string $raw): string
    {
        if (! $raw) {
            return '/';
        }

        return ($raw !== '/' && str_starts_with($raw, '/') && ! str_starts_with($raw, '//')) ? $raw : '/';
    }
}
