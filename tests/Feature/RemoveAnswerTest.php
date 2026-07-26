<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Livewire\CreatorQuestionDetail;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin moderation: hiding and restoring individual answers.
 */
class RemoveAnswerTest extends TestCase
{
    use RefreshDatabase;

    private function answered(User $creator, string $body = 'The main answer to this question.'): Question
    {
        return Question::factory()
            ->answeredBy($creator, $body)
            ->create(['asked_by' => User::factory()->create()->id]);
    }

    public function test_an_admin_can_remove_an_alternative(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main, 'The main answer that stays.');
        $alternative = $q->addAlternativeAnswerFrom($other, 'The alternative being removed.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('removeAnswer', $alternative->id)
            ->assertHasNoErrors();

        $fresh = $q->fresh();
        $this->assertTrue($alternative->fresh()->trashed());
        // The question is untouched — only the alternative went away.
        $this->assertSame(QuestionStatus::Answered, $fresh->status);
        $this->assertSame('The main answer that stays.', $fresh->primaryAnswer->body);
        $this->assertSame(1, $fresh->answers()->count());
    }

    public function test_a_removed_alternative_disappears_from_the_public_page(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'The alternative being removed.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('removeAnswer', $alternative->id);

        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertDontSee('The alternative being removed.');
    }

    public function test_removing_the_main_answer_reopens_the_question(): void
    {
        $main  = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main, 'The main answer being removed.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('removeAnswer', $q->primary_answer_id)
            ->assertHasNoErrors();

        $fresh = $q->fresh();
        $this->assertSame(QuestionStatus::Asked, $fresh->status);
        $this->assertNull($fresh->claimed_by);
        $this->assertTrue($fresh->hasHiddenAnswer());
    }

    public function test_an_admin_can_restore_a_removed_alternative(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'The alternative coming back.');
        $q->removeAnswer($alternative);

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q->fresh()])
            ->call('restoreAnswer', $alternative->id)
            ->assertHasNoErrors();

        $this->assertFalse($alternative->fresh()->trashed());

        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('The alternative coming back.');
    }

    public function test_removed_answers_are_listed_for_admins_only(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'The removed alternative body.');
        $q->removeAnswer($alternative);

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q->fresh()])
            ->assertSee('1 removed answer')
            ->assertSee('The removed alternative body.');

        // Not even its own author sees it once it is down.
        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q->fresh()])
            ->assertDontSee('removed answer')
            ->assertDontSee('The removed alternative body.');
    }

    public function test_creators_cannot_remove_or_restore(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();

        $q = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'The alternative they cannot remove.');

        // Not even its own author may take it down.
        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('removeAnswer', $alternative->id)
            ->assertHasErrors('moderate');

        $this->assertFalse($alternative->fresh()->trashed());

        $q->removeAnswer($alternative);

        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q->fresh()])
            ->call('restoreAnswer', $alternative->id)
            ->assertHasErrors('moderate');

        $this->assertTrue($alternative->fresh()->trashed());
    }

    public function test_an_answer_on_another_question_cannot_be_removed(): void
    {
        $creator = User::factory()->creator()->create();
        $admin   = User::factory()->admin()->create();

        $mine  = $this->answered($creator, 'The answer on this question.');
        $other = $this->answered($creator, 'The answer on a different question.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $mine])
            ->call('removeAnswer', $other->primary_answer_id)
            ->assertHasErrors('moderate');

        $this->assertFalse($other->fresh()->primaryAnswer->trashed());
    }

    public function test_an_already_removed_answer_cannot_be_removed_again(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'The alternative already down.');
        $q->removeAnswer($alternative);
        $removedAt = $alternative->fresh()->deleted_at;

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q->fresh()])
            ->call('removeAnswer', $alternative->id)
            ->assertHasErrors('moderate');

        $this->assertEquals($removedAt, $alternative->fresh()->deleted_at);
    }

    public function test_a_restored_answer_returns_as_an_alternative_when_the_slot_was_refilled(): void
    {
        $first  = User::factory()->creator()->create();
        $second = User::factory()->creator()->create();
        $admin  = User::factory()->admin()->create();

        $q = $this->answered($first, 'The first main answer.');
        $removed = $q->primaryAnswer;

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('removeAnswer', $removed->id);

        // Someone else claims the reopened question and answers it.
        $q = $q->fresh();
        $q->claimBy($second);
        $q->fresh()->publishPrimaryAnswerFrom($second, 'The replacement main answer.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q->fresh()])
            ->call('restoreAnswer', $removed->id)
            ->assertHasNoErrors();

        $fresh = $q->fresh()->load('answers');
        $this->assertSame('The replacement main answer.', $fresh->primaryAnswer->body);
        $this->assertSame(['The first main answer.'], $fresh->otherAnswers()->pluck('body')->all());
    }

    public function test_the_remove_button_is_shown_to_admins_only(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main);
        $q->addAlternativeAnswerFrom($other, 'An alternative on the page.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertSee('Remove');

        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertDontSee('Remove');
    }
}
