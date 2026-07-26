<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A question carries the claimer's main answer plus alternative answers from
 * other creators.
 */
class AlternativeAnswersTest extends TestCase
{
    use RefreshDatabase;

    private function answered(User $creator, string $body = 'The main answer to this question.'): Question
    {
        return Question::factory()
            ->answeredBy($creator, $body)
            ->create(['asked_by' => User::factory()->create()->id]);
    }

    public function test_another_creator_can_add_an_alternative_answer(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);

        $alternative = $q->addAlternativeAnswerFrom($other, 'A different way to look at it entirely.');

        $this->assertNotNull($alternative);
        $this->assertSame($other->id, $alternative->created_by);
        $this->assertFalse($alternative->isPrimary());
        $this->assertSame(2, $q->answers()->count());
    }

    public function test_an_alternative_does_not_displace_the_main_answer(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main, 'The main answer stays put.');

        $q->addAlternativeAnswerFrom($other, 'An alternative that must not take over.');

        $fresh = $q->fresh();
        $this->assertSame(QuestionStatus::Answered, $fresh->status);
        $this->assertSame('The main answer stays put.', $fresh->primaryAnswer->body);
        $this->assertSame($main->id, $fresh->primaryAnswer->created_by);
    }

    public function test_no_alternative_before_a_main_answer_exists(): void
    {
        $creator = User::factory()->creator()->create();
        $other   = User::factory()->creator()->create();

        $open    = Question::factory()->create(['asked_by' => User::factory()->create()->id]);
        $claimed = Question::factory()->claimedBy($creator)->create(['asked_by' => User::factory()->create()->id]);

        $this->assertFalse($open->isAnswerableBy($other));
        $this->assertFalse($claimed->isAnswerableBy($other));
        $this->assertNull($open->addAlternativeAnswerFrom($other, 'Too early for this answer.'));
        $this->assertSame(0, $open->answers()->count());
    }

    public function test_a_creator_gets_only_one_answer_per_question(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);

        $q->addAlternativeAnswerFrom($other, 'My one and only alternative.');

        $this->assertFalse($q->fresh()->isAnswerableBy($other));
        $this->assertNull($q->fresh()->addAlternativeAnswerFrom($other, 'A sneaky second alternative.'));
        $this->assertSame(2, $q->answers()->count());
    }

    public function test_the_main_answerer_cannot_add_an_alternative_to_their_own(): void
    {
        $main = User::factory()->creator()->create();
        $q    = $this->answered($main);

        $this->assertFalse($q->isAnswerableBy($main));
        $this->assertNull($q->addAlternativeAnswerFrom($main, 'A second bite at the same cherry.'));
    }

    public function test_members_and_guests_cannot_add_an_alternative(): void
    {
        $main   = User::factory()->creator()->create();
        $member = User::factory()->create();
        $q      = $this->answered($main);

        $this->assertFalse($q->isAnswerableBy($member));
        $this->assertFalse($q->isAnswerableBy(null));
        $this->assertNull($q->addAlternativeAnswerFrom($member, 'A member trying to answer here.'));
    }

    public function test_the_database_refuses_a_second_row_from_the_same_creator(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);

        $q->answers()->create(['created_by' => $other->id, 'body' => 'First one.', 'published_at' => now()]);

        // The model guards this, but the index is what makes two concurrent
        // requests unable to both land an answer.
        $this->expectException(UniqueConstraintViolationException::class);
        $q->answers()->create(['created_by' => $other->id, 'body' => 'Second one.', 'published_at' => now()]);
    }

    public function test_other_answers_lists_alternatives_oldest_first(): void
    {
        $main   = User::factory()->creator()->create();
        $second = User::factory()->creator()->create();
        $third  = User::factory()->creator()->create();
        $q      = $this->answered($main);

        $this->travel(1)->minutes();
        $q->addAlternativeAnswerFrom($second, 'The earlier alternative answer.');
        $this->travel(1)->minutes();
        $q->addAlternativeAnswerFrom($third, 'The later alternative answer.');

        $others = $q->fresh()->load('answers')->otherAnswers();

        $this->assertSame(
            ['The earlier alternative answer.', 'The later alternative answer.'],
            $others->pluck('body')->all(),
        );
    }

    public function test_removing_the_main_answer_reopens_the_question_and_keeps_alternatives(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);
        $q->addAlternativeAnswerFrom($other, 'An alternative that survives moderation.');

        $q->removeAnswer($q->primaryAnswer);

        $fresh = $q->fresh();
        $this->assertSame(QuestionStatus::Asked, $fresh->status);
        $this->assertNull($fresh->claimed_by);
        $this->assertFalse($fresh->hasVisibleAnswer());
        $this->assertTrue($fresh->hasHiddenAnswer());
        $this->assertSame(1, $fresh->answers()->count());
    }

    public function test_removing_an_alternative_leaves_the_question_answered(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'An alternative about to be removed.');

        $q->removeAnswer($alternative);

        $fresh = $q->fresh();
        $this->assertSame(QuestionStatus::Answered, $fresh->status);
        $this->assertTrue($fresh->hasVisibleAnswer());
        $this->assertSame(1, $fresh->answers()->count());
    }

    public function test_an_alternative_can_be_promoted_into_the_main_slot(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'The alternative that gets promoted.');

        $q->removeAnswer($q->primaryAnswer);
        $q->fresh()->promoteToPrimary($alternative);

        $fresh = $q->fresh();
        $this->assertSame(QuestionStatus::Answered, $fresh->status);
        $this->assertSame($alternative->id, $fresh->primary_answer_id);
        $this->assertSame($other->id, $fresh->claimed_by);
        $this->assertTrue($alternative->fresh()->isPrimary());
    }

    public function test_a_restored_answer_returns_as_an_alternative_when_the_slot_was_refilled(): void
    {
        $first  = User::factory()->creator()->create();
        $second = User::factory()->creator()->create();
        $q      = $this->answered($first, 'The answer that gets removed and restored.');

        $removed = $q->primaryAnswer;
        $q->removeAnswer($removed);

        // Someone else claims the reopened question and answers it.
        $q = $q->fresh();
        $q->claimBy($second);
        $q->fresh()->publishPrimaryAnswerFrom($second, 'The replacement main answer.');

        $q = $q->fresh();
        $q->restoreAnswer($removed);

        $fresh = $q->fresh()->load('answers');
        $this->assertSame('The replacement main answer.', $fresh->primaryAnswer->body);
        $this->assertSame(
            ['The answer that gets removed and restored.'],
            $fresh->otherAnswers()->pluck('body')->all(),
        );
    }

    public function test_alternatives_count_toward_a_creators_public_answer_total(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create(['name' => 'Alternative Ann']);
        $q     = $this->answered($main);
        $q->addAlternativeAnswerFrom($other, 'An alternative that should be credited.');

        $this->get(route('creators.show', $other))
            ->assertOk()
            ->assertSee('1 answer published');
    }

    public function test_the_answered_history_lists_questions_answered_as_an_alternative(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();

        $mine = $this->answered($main);
        $mine->update(['content' => 'Answered as an alternative']);
        $mine->addAlternativeAnswerFrom($other, 'My alternative take on this one.');

        $theirs = $this->answered($main);
        $theirs->update(['content' => 'Not answered by me at all']);

        $this->actingAs($other)->get('/creator/answered')
            ->assertOk()
            ->assertSee('Answered as an alternative')
            ->assertDontSee('Not answered by me at all');
    }
}
