<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\UserRoleInvite;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserInviter
{
    /**
     * Invite a user by email.
     *
     * - If they already have an account: upgrade their role.
     * - If they don't: create the account (email_verified_at = now(), admin vouches)
     *   and send a password-reset link so they can set their own password.
     *
     * Either way send the UserRoleInvite Mailable telling them they have access.
     *
     * @return 'created' | 'upgraded'
     */
    public function invite(string $email, string $name, UserRole $role): string
    {
        $existing = User::where('email', $email)->first();

        if ($existing) {
            $existing->update(['role' => $role]);

            Mail::to($existing)->send(new UserRoleInvite($role));

            return 'upgraded';
        }

        // Unusable password until they go through the reset flow
        $randomPassword = Str::random(64);

        $user = User::create([
            'name'              => $name,
            'email'             => $email,
            'password'          => Hash::make($randomPassword),
            'email_verified_at' => now(),
            'role'              => $role,
        ]);

        // Send a password-reset link so the invitee can set their own password
        $token = app('auth.password.broker')->createToken($user);
        $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email], false);
        $resetUrl = config('app.url') . $resetUrl;

        Mail::to($user)->send(new UserRoleInvite($role, $resetUrl));

        return 'created';
    }
}