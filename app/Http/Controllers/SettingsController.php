<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmNewEmail;
use App\Models\PendingEmailChange;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings', ['user' => $request->user()]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
        ], [
            'name.required' => 'Nickname is required.',
            'name.min'      => 'Nickname must be 2–40 characters.',
            'name.max'      => 'Nickname must be 2–40 characters.',
        ]);

        $request->user()->update(['name' => $validated['name']]);

        return Redirect::route('settings')->with('status', 'name-updated');
    }

    public function changeEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'newEmail' => ['required', 'email'],
        ], [
            'newEmail.required' => 'Enter a valid email address.',
            'newEmail.email'     => 'Enter a valid email address.',
        ]);

        $newEmail = strtolower($validated['newEmail']);
        $user     = $request->user();

        if ($newEmail === strtolower($user->email)) {
            return Redirect::route('settings')
                ->withErrors(['newEmail' => 'That is already your current email address.']);
        }

        if (User::where('email', $newEmail)->exists()) {
            return Redirect::route('settings')
                ->withErrors(['newEmail' => 'That email address is already in use.']);
        }

        // Invalidate any previous pending change for this user
        PendingEmailChange::where('user_id', $user->id)->delete();

        $pending = PendingEmailChange::create([
            'user_id'    => $user->id,
            'new_email'  => $newEmail,
            'token'      => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);

        $url = url(route('email.change.confirm', ['token' => $pending->token], false));

        Mail::to($newEmail)->send(new ConfirmNewEmail($url));

        return Redirect::route('settings')->with([
            'status'    => 'email-pending',
            'newEmail'   => $newEmail,
        ]);
    }

    public function confirmNewEmail(Request $request, string $token): RedirectResponse
    {
        $pending = PendingEmailChange::where('token', $token)->first();

        if (! $pending) {
            return Redirect::route('settings')
                ->withErrors(['email' => 'Invalid or unknown confirmation link.']);
        }

        if ($pending->isExpired()) {
            $pending->delete();
            return Redirect::route('settings')
                ->withErrors(['email' => 'This confirmation link has expired. Please request a new one.']);
        }

        if (User::where('email', $pending->new_email)->exists()) {
            $pending->delete();
            return Redirect::route('settings')
                ->withErrors(['email' => 'That email address is already in use.']);
        }

        $user = $pending->user;
        $user->update([
            'email'             => $pending->new_email,
            'email_verified_at' => now(),
        ]);
        $pending->delete();

        return Redirect::route('settings')->with('status', 'email-confirmed');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword'     => ['required', 'string', 'min:8'],
            'confirmPassword' => ['required', 'string'],
        ], [
            'currentPassword.required' => 'Current password is required.',
            'newPassword.required'     => 'New password is required.',
            'newPassword.min'          => 'New password must be at least 8 characters.',
            'confirmPassword.required' => 'Please confirm your new password.',
        ]);

        if ($validated['newPassword'] !== $validated['confirmPassword']) {
            return Redirect::route('settings')
                ->withErrors(['confirmPassword' => 'Passwords do not match.']);
        }

        $user = $request->user();

        if (! Hash::check($validated['currentPassword'], $user->password)) {
            return Redirect::route('settings')
                ->withErrors(['currentPassword' => 'Current password is incorrect.']);
        }

        $user->update(['password' => $validated['newPassword']]);

        return Redirect::route('settings')->with('status', 'password-updated');
    }
}