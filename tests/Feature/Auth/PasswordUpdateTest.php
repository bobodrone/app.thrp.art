<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings')
            ->post('/settings/password', [
                'currentPassword'    => 'password',
                'newPassword'        => 'new-password',
                'confirmPassword'    => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings')
            ->post('/settings/password', [
                'currentPassword'    => 'wrong-password',
                'newPassword'        => 'new-password',
                'confirmPassword'    => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors(['currentPassword'])
            ->assertRedirect('/settings');
    }
}
