<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:40'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', 'min:8', Rules\Password::defaults()],
        ], [
            'name.min'      => 'Nickname must be 2–40 characters.',
            'name.max'      => 'Nickname must be 2–40 characters.',
            'password.min'  => 'Password must be at least 8 characters.',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Dispatches the VerifyEmail notification (MustVerifyEmail on User model)
        event(new Registered($user));

        // Do NOT log the user in — show the "Check your email" screen instead,
        // mirroring the SvelteKit app's registration flow.

        return redirect()->route('register')->with([
            'registered' => true,
            'email'      => $user->email,
        ]);
    }
}