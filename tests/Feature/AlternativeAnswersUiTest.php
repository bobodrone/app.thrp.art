<?php

namespace Tests\Feature;

use App\Livewire\CreatorQuestionDetail;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rendering and submitting alternative answers.
 */
class AlternativeAnswersUiTest extends TestCase
{
    use RefreshDatabase;

    private function answered(User $creator, string $body = 'The main answer to this question.'): Question
    {
        return Question::factory()
            ->answeredBy($creator, $body)
            ->create(['asked_by' => User::factory()->create()->id]);
    }

    public function test_the_question_page_shows_alternatives_under_the_main_answer(): void
    {
        $main  = User::factory()->creator()->create(['name' => 'Main Creator']);
        $other = User::factory()->creator()->create(['name' => 'Other Creator']);
        $q     = $this->answered($main, 'The main answer body here.');
        $q->addAlternativeAnswerFrom($other, 'The alternative answer body here.');

        $response = $this->get(route('questions.show', $q));

        $response->assertOk()
            ->assertSee('The main answer body here.')
            ->assertSee('The alternative answer body here.')
            ->assertSee('1 other answer from the community')
            ->assertSee('Other Creator');

        // The main answer still comes first on the page.
        $this->assertLessThan(
            strpos($response->getContent(), 'The alternative answer body here.'),
            strpos($response->getContent(), 'The main answer body here.'),
        );
    }

    public function test_a_question_with_one_answer_shows_no_alternatives_section(): void
    {
        $q = $this->answered(User::factory()->creator()->create());

        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertDontSee('from the community');
    }

    public function test_alternatives_are_pluralised_on_the_question_page(): void
    {
        $main = User::factory()->creator()->create();
        $q    = $this->answered($main);
        $q->addAlternativeAnswerFrom(User::factory()->creator()->create(), 'The first alternative answer.');
        $q->addAlternativeAnswerFrom(User::factory()->creator()->create(), 'The second alternative answer.');

        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('2 other answers from the community');
    }

    public function test_a_removed_alternative_is_not_shown(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'An alternative an admin removed.');

        $q->removeAnswer($alternative);

        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertDontSee('An alternative an admin removed.')
            ->assertDontSee('from the community');
    }

    public function test_the_card_counts_every_answer_on_the_question(): void
    {
        $main = User::factory()->creator()->create();
        $q    = $this->answered($main);
        $q->addAlternativeAnswerFrom(User::factory()->creator()->create(), 'An alternative on the feed card.');

        $this->get('/')
            ->assertOk()
            ->assertSee('2 answers — click to read')
            ->assertSee('+1 other answer from the community →');
    }

    public function test_a_single_answer_card_keeps_the_original_hint(): void
    {
        $this->answered(User::factory()->creator()->create());

        $this->get('/')
            ->assertOk()
            ->assertSee('Has an answer — click to read')
            ->assertDontSee('from the community');
    }

    public function test_a_creator_without_an_answer_is_offered_a_way_in(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);

        $this->actingAs($other)->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('Have a different take on this one?')
            ->assertSee('Add your answer →');
    }

    public function test_the_main_answerer_and_members_are_not_offered_a_way_in(): void
    {
        $main = User::factory()->creator()->create();
        $q    = $this->answered($main);

        $this->actingAs($main)->get(route('questions.show', $q))
            ->assertOk()
            ->assertDontSee('Add your answer →');

        $this->actingAs(User::factory()->create())->get(route('questions.show', $q))
            ->assertOk()
            ->assertDontSee('Add your answer →');
    }

    public function test_a_creator_can_post_an_alternative_from_the_creator_view(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);

        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertViewHas('canAddAlternative', true)
            ->set('alternative', 'My alternative take, long enough to pass validation.')
            ->call('submitAlternative')
            ->assertHasNoErrors();

        $fresh = $q->fresh()->load('answers');
        $this->assertSame(2, $fresh->answers->count());
        $this->assertSame(
            'My alternative take, long enough to pass validation.',
            $fresh->otherAnswers()->first()->body,
        );
        // The main answer is untouched.
        $this->assertSame($main->id, $fresh->primaryAnswer->created_by);
    }

    public function test_a_short_alternative_is_rejected(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);

        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('alternative', 'short')
            ->call('submitAlternative')
            ->assertHasErrors('alternative');

        $this->assertSame(1, $q->answers()->count());
    }

    public function test_the_alternative_form_is_hidden_from_creators_who_already_answered(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);
        $q->addAlternativeAnswerFrom($other, 'The answer they already posted.');

        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertViewHas('canAddAlternative', false);

        Livewire::actingAs($main)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertViewHas('canAddAlternative', false);
    }

    public function test_a_creator_can_edit_their_own_alternative(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'The first draft of my alternative.');

        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer', $alternative->id)
            ->assertSet('editingAnswerId', $alternative->id)
            ->assertSet('answerDraft', 'The first draft of my alternative.')
            ->set('answerDraft', 'The revised version of my alternative.')
            ->call('updateAnswer')
            ->assertHasNoErrors()
            ->assertSet('editingAnswerId', null);

        $this->assertSame('The revised version of my alternative.', $alternative->fresh()->body);
    }

    public function test_a_creator_cannot_edit_someone_elses_alternative(): void
    {
        $main    = User::factory()->creator()->create();
        $other   = User::factory()->creator()->create();
        $meddler = User::factory()->creator()->create();
        $q       = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'The protected alternative body.');

        Livewire::actingAs($meddler)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer', $alternative->id)
            ->assertSet('editingAnswerId', null)
            ->set('answerDraft', 'A malicious rewrite of the alternative.')
            ->call('updateAnswer')
            ->assertHasErrors('answerDraft');

        $this->assertSame('The protected alternative body.', $alternative->fresh()->body);
    }

    public function test_an_admin_can_edit_any_alternative(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();
        $admin = User::factory()->admin()->create();
        $q     = $this->answered($main);
        $alternative = $q->addAlternativeAnswerFrom($other, 'An alternative needing moderation.');

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer', $alternative->id)
            ->set('answerDraft', 'An alternative an admin corrected.')
            ->call('updateAnswer')
            ->assertHasNoErrors();

        $this->assertSame('An alternative an admin corrected.', $alternative->fresh()->body);
    }

    public function test_editing_cannot_reach_an_answer_on_another_question(): void
    {
        $creator = User::factory()->creator()->create();
        $mine    = $this->answered($creator, 'The answer I am allowed to edit.');
        $other   = $this->answered($creator, 'An answer on a different question.');

        // Same author, so isEditableBy passes — only the question check stops it.
        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $mine])
            ->call('startEditAnswer', $other->primary_answer_id)
            ->assertSet('editingAnswerId', null)
            ->set('answerDraft', 'A rewrite aimed at the wrong question.')
            ->call('updateAnswer')
            ->assertHasErrors('answerDraft');

        $this->assertSame('An answer on a different question.', $other->fresh()->primaryAnswer->body);
    }

    public function test_the_edit_target_cannot_be_set_from_the_browser(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = $this->answered($creator);

        // #[Locked] is what stops a tampered payload from pointing the edit
        // form at an answer the creator never opened.
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('editingAnswerId', $q->primary_answer_id);
    }
}
