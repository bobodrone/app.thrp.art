<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\AdminUserManagement;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Anonymising stands in for deleting, and the reason is in the schema:
 * questions.asked_by cascades on delete, so removing the row would take the
 * person's questions — and the responders' answers on them — down with it.
 * The person goes; what was written stays.
 */
class AnonymiseUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_person_goes_and_their_questions_stay(): void
    {
        $admin     = User::factory()->admin()->create();
        $responder = User::factory()->creator()->create();
        $asker     = User::factory()->create([
            'name'  => 'Real Name',
            'email' => 'real@example.com',
            'bio'   => 'All about me.',
        ]);

        $question = Question::factory()->answeredBy($responder, 'Water it less.')
            ->create(['asked_by' => $asker->id]);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('anonymise', $asker->id)
            ->assertHasNoErrors();

        $fresh = $asker->fresh();
        $this->assertTrue($fresh->isAnonymised());
        $this->assertSame('Deleted user', $fresh->name);
        $this->assertStringNotContainsString('real@example.com', $fresh->email);
        $this->assertStringEndsWith('@' . User::ANONYMISED_EMAIL_DOMAIN, $fresh->email);
        $this->assertNull($fresh->bio);
        $this->assertNull($fresh->email_verified_at);

        // The question and the answer written on it are untouched.
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'asked_by' => $asker->id]);
        $this->assertSame('Water it less.', $question->fresh()->primaryAnswer->body);
    }

    public function test_an_anonymised_account_cannot_be_signed_into(): void
    {
        // Driven through the model rather than the component: signing in has to
        // happen as a guest, and Livewire::actingAs would leave an admin logged in.
        $user = User::factory()->create(['email' => 'gone@example.com']);

        $user->anonymise();

        $this->post('/login', ['email' => 'gone@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_anonymised_responder_is_demoted_to_member(): void
    {
        $admin     = User::factory()->admin()->create();
        $responder = User::factory()->creator()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('anonymise', $responder->id)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Member, $responder->fresh()->role);
    }

    public function test_an_admin_cannot_anonymise_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('anonymise', $admin->id)
            ->assertHasErrors(['anonymise_' . $admin->id]);

        $this->assertFalse($admin->fresh()->isAnonymised());
    }

    public function test_the_last_remaining_admin_cannot_be_anonymised(): void
    {
        $a1 = User::factory()->admin()->create();

        Livewire::actingAs($a1)
            ->test(AdminUserManagement::class)
            ->call('anonymise', $a1->id)
            ->assertHasErrors(['anonymise_' . $a1->id]);

        $this->assertFalse($a1->fresh()->isAnonymised());
    }
}
