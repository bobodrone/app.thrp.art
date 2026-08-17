<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class AdminBootstrapController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        // Already admin → nothing to do
        if ($request->user() && $request->user()->role === UserRole::Admin) {
            return Redirect::route('admin.users');
        }

        // Self-disable once any admin exists
        if (User::where('role', UserRole::Admin)->exists()) {
            abort(403, 'Setup already complete — an admin already exists.');
        }

        return view('admin.setup');
    }

    public function store(Request $request): View|RedirectResponse
    {
        // Don't trust the GET check alone — recheck on submit
        if (User::where('role', UserRole::Admin)->exists()) {
            abort(403, 'Setup already complete.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $bootstrapToken = config('app.bootstrap_token');

        if (empty($bootstrapToken)) {
            return Redirect::back()
                ->withInput($request->only('email'))
                ->withErrors(['token' => 'BOOTSTRAP_TOKEN is not set on the server — set it in .env before running setup.']);
        }

        if (! hash_equals($bootstrapToken, $validated['token'])) {
            return Redirect::back()
                ->withInput($request->only('email'))
                ->withErrors(['token' => 'Invalid bootstrap token.']);
        }

        $target = User::where('email', $validated['email'])->first();

        if (! $target) {
            return Redirect::back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'No account found for that email — register first, then come back here.']);
        }

        $target->update(['role' => UserRole::Admin]);

        return Redirect::route('admin.users');
    }
}
