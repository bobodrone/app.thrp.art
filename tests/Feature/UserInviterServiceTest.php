<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\UserRoleInvite;
use App\Models\User;
use App\Services\UserInviter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserInviterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_user_when_email_unknown(): void
    {
        Mail::fake();
        $inviter = app(UserInviter::class);

        $result = $inviter->invite('new@example.com', 'New Person', UserRole::Creator);

        $this->assertSame('created', $result);
        $u = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($u);
        $this->assertSame(UserRole::Creator, $u->role);
        $this->assertNotNull($u->email_verified_at);
        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Creator);
    }

    public function test_upgrades_existing_user_when_email_known(): void
    {
        Mail::fake();
        $member = User::factory()->create(['role' => UserRole::Member, 'email' => 'existing@example.com']);
        $inviter = app(UserInviter::class);

        $result = $inviter->invite('existing@example.com', 'Old Name', UserRole::Admin);

        $this->assertSame('upgraded', $result);
        $this->assertSame(UserRole::Admin, $member->refresh()->role);
        $this->assertDatabaseCount('users', 1);
        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Admin);
    }
}
