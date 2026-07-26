<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Livewire\AdminQuestionsTable;
use App\Livewire\CreatorQuestionDetail;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin moderation: swapping which answer sits in the main slot.
 */
class PromoteAnswerTest extends TestCase
{
    use RefreshDatabase;

    private function answered(User $creator, string $body = 'The main answer to this question.'): Question
    {
        return Question::factory()
            ->answeredBy($creator, $body)
            ->create(['asked_by' => User::factory()->create()->id]);
    }

    public function test_an_admin_can_promote_an_alternative(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main, 'The answer being demoted.');
        $alternative = $q->addAlternativeAnswerFrom($other, 'The answer being promoted.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('promoteAnswer', $alternative->id)
            ->assertHasNoErrors();

        $fresh = $q->fresh();
        $this->assertSame($alternative->id, $fresh->primary_answer_id);
        $this->assertSame('The answer being promoted.', $fresh->primaryAnswer->body);
        $this->assertSame($other->id, $fresh->claimed_by);
    }

    public function test_the_demoted_answer_stays_on_as_an_alternative(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main, 'The answer being demoted.');
        $alternative = $q->addAlternativeAnswerFrom($other, 'The answer being promoted.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('promoteAnswer', $alternative->id);

        $fresh = $q->fresh()->load('answers');
        $this->assertSame(2, $fresh->answers->count());
        $this->assertSame(
            ['The answer being demoted.'],
            $fresh->otherAnswers()->pluck('body')->all(),
        );
    }

    public function test_promoting_reanswers_a_question_whose_main_answer_was_removed(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'The alternative that saves the day.');
        $q->removeAnswer($q->primaryAnswer);

        $this->assertSame(QuestionStatus::Asked, $q->fresh()->status);

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q->fresh()])
            ->call('promoteAnswer', $alternative->id)
            ->assertHasNoErrors();

        $fresh = $q->fresh();
        $this->assertSame(QuestionStatus::Answered, $fresh->status);
        $this->assertSame($alternative->id, $fresh->primary_answer_id);
    }

    public function test_creators_cannot_promote(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();

        $q = $this->answered($main, 'The answer that stays put.');
        $alternative = $q->addAlternativeAnswerFrom($other, 'The answer they want promoted.');

        // Not even the author of the alternative may promote their own.
        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('promoteAnswer', $alternative->id)
            ->assertHasErrors('promote');

        $this->assertSame('The answer that stays put.', $q->fresh()->primaryAnswer->body);
    }

    public function test_a_hidden_answer_cannot_be_promoted(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main, 'The answer that stays put.');
        $removed = $q->addAlternativeAnswerFrom($other, 'An alternative an admin removed.');
        $q->removeAnswer($removed);

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('promoteAnswer', $removed->id)
            ->assertHasErrors('promote');

        $this->assertSame('The answer that stays put.', $q->fresh()->primaryAnswer->body);
    }

    public function test_an_answer_on_another_question_cannot_be_promoted(): void
    {
        $creator = User::factory()->creator()->create();
        $admin   = User::factory()->admin()->create();

        $mine  = $this->answered($creator, 'The answer on this question.');
        $other = $this->answered($creator, 'The answer on a different question.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $mine])
            ->call('promoteAnswer', $other->primary_answer_id)
            ->assertHasErrors('promote');

        $this->assertSame('The answer on this question.', $mine->fresh()->primaryAnswer->body);
    }

    public function test_the_promote_button_is_shown_to_admins_only(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $q = $this->answered($main);
        $q->addAlternativeAnswerFrom($other, 'An alternative awaiting promotion.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertSee('Make main answer');

        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertDontSee('Make main answer');
    }

    public function test_the_admin_table_links_to_questions_with_several_answers(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();

        $single = $this->answered($main);
        $single->update(['content' => 'The question with one answer']);

        $several = $this->answered($main);
        $several->update(['content' => 'The question with several answers']);
        $several->addAlternativeAnswerFrom($other, 'The alternative on this one.');

        Livewire::actingAs($admin)
            ->test(AdminQuestionsTable::class)
            ->assertSee('Answers (2)')
            ->assertSee(route('creator.questions.show', $several), escape: false)
            ->assertDontSee(route('creator.questions.show', $single), escape: false);
    }
}
