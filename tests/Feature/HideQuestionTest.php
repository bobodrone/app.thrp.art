<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Livewire\AdminQuestionsTable;
use App\Livewire\CreatorDashboard;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin moderation: taking a whole question out of public view.
 *
 * Hiding sits beside deleting rather than replacing it. Delete is the silent,
 * total option; hide leaves the question live, keeps the asker in the loop and
 * carries an optional reason written for them.
 */
class HideQuestionTest extends TestCase
{
    use RefreshDatabase;

    private function question(array $attributes = []): Question
    {
        return Question::factory()->create($attributes + [
            'content' => 'Why do my tomatoes split open on the vine?',
        ]);
    }

    // ── The admin action ──────────────────────────────────────────────────

    public function test_an_admin_can_hide_a_question_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $q     = $this->question();

        Livewire::actingAs($admin)
            ->test(AdminQuestionsTable::class)
            ->call('confirmHide', $q->id)
            ->assertSet('showHide', true)
            ->set('hideReason', 'Please rephrase without naming a specific person.')
            ->call('hide')
            ->assertHasNoErrors()
            ->assertSet('showHide', false);

        $fresh = $q->fresh();
        $this->assertTrue($fresh->isHidden());
        $this->assertSame($admin->id, $fresh->hidden_by);
        $this->assertSame('Please rephrase without naming a specific person.', $fresh->hidden_reason);
        // Hiding is orthogonal to the lifecycle — the status is untouched.
        $this->assertSame(QuestionStatus::Asked, $fresh->status);
        $this->assertFalse($fresh->trashed());
    }

    public function test_the_reason_is_optional(): void
    {
        $admin = User::factory()->admin()->create();
        $q     = $this->question();

        Livewire::actingAs($admin)
            ->test(AdminQuestionsTable::class)
            ->call('confirmHide', $q->id)
            ->call('hide')
            ->assertHasNoErrors();

        $this->assertTrue($q->fresh()->isHidden());
        $this->assertNull($q->fresh()->hidden_reason);
    }

    public function test_a_whitespace_only_reason_is_stored_as_none(): void
    {
        $admin = User::factory()->admin()->create();
        $q     = $this->question();

        Livewire::actingAs($admin)
            ->test(AdminQuestionsTable::class)
            ->call('confirmHide', $q->id)
            ->set('hideReason', "   \n  ")
            ->call('hide')
            ->assertHasNoErrors();

        $this->assertNull($q->fresh()->hidden_reason);
    }

    public function test_an_over_long_reason_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $q     = $this->question();

        Livewire::actingAs($admin)
            ->test(AdminQuestionsTable::class)
            ->call('confirmHide', $q->id)
            ->set('hideReason', str_repeat('a', 1001))
            ->call('hide')
            ->assertHasErrors('hideReason');

        $this->assertFalse($q->fresh()->isHidden());
    }

    public function test_an_admin_can_unhide_a_question_and_the_reason_goes_with_it(): void
    {
        $admin = User::factory()->admin()->create();
        $q     = Question::factory()->hidden($admin, 'Off topic.')->create();

        Livewire::actingAs($admin)
            ->test(AdminQuestionsTable::class)
            ->call('unhide', $q->id)
            ->assertHasNoErrors();

        $fresh = $q->fresh();
        $this->assertFalse($fresh->isHidden());
        $this->assertNull($fresh->hidden_by);
        $this->assertNull($fresh->hidden_reason);
    }

    public function test_hiding_sends_no_mail(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $q     = $this->question();

        Livewire::actingAs($admin)
            ->test(AdminQuestionsTable::class)
            ->call('confirmHide', $q->id)
            ->set('hideReason', 'Not a question for this community.')
            ->call('hide');

        Mail::assertNothingSent();
    }

    // ── The admin table ───────────────────────────────────────────────────

    public function test_the_admin_table_lists_hidden_questions_and_badges_them(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        Question::factory()->hidden($admin, 'Naming a private individual.')
            ->create(['content' => 'A hidden question']);

        $this->actingAs($admin)->get('/admin/questions')
            ->assertOk()
            ->assertSee('A hidden question')
            ->assertSee('Hidden')
            ->assertSee('Naming a private individual.');
    }

    public function test_the_hidden_only_filter_narrows_the_table(): void
    {
        $admin = User::factory()->admin()->create();
        Question::factory()->create(['content' => 'A public question']);
        Question::factory()->hidden($admin)->create(['content' => 'A hidden question']);

        Livewire::actingAs($admin)
            ->test(AdminQuestionsTable::class)
            ->set('hiddenOnly', true)
            ->assertSee('A hidden question')
            ->assertDontSee('A public question');
    }

    // ── Public exclusion ──────────────────────────────────────────────────

    public function test_a_hidden_question_leaves_the_public_feed(): void
    {
        Question::factory()->create(['content' => 'A public question']);
        Question::factory()->hidden()->create(['content' => 'A hidden question']);

        $this->get('/')
            ->assertOk()
            ->assertSee('A public question')
            ->assertDontSee('A hidden question');
    }

    public function test_the_public_detail_page_of_a_hidden_question_is_a_404(): void
    {
        $q       = Question::factory()->hidden()->create();
        $other   = User::factory()->create();
        $creator = User::factory()->creator()->create();

        $this->get(route('questions.show', $q))->assertNotFound();
        $this->actingAs($other)->get(route('questions.show', $q))->assertNotFound();
        $this->actingAs($creator)->get(route('questions.show', $q))->assertNotFound();
    }

    public function test_a_hidden_question_leaves_the_responder_open_list(): void
    {
        $creator = User::factory()->creator()->create();
        Question::factory()->create(['content' => 'An open question']);
        Question::factory()->hidden()->create(['content' => 'A hidden question']);

        Livewire::actingAs($creator)
            ->test(CreatorDashboard::class)
            ->assertSee('An open question')
            ->assertDontSee('A hidden question');
    }

    public function test_a_hidden_question_leaves_the_answered_list(): void
    {
        $creator = User::factory()->creator()->create();
        $admin   = User::factory()->admin()->create();

        $shown  = Question::factory()->answeredBy($creator)->create(['content' => 'A visible answered question']);
        $hidden = Question::factory()->answeredBy($creator)->create(['content' => 'A hidden answered question']);
        $hidden->hide($admin);

        $this->actingAs($creator)->get(route('creator.answered'))
            ->assertOk()
            ->assertSee('A visible answered question')
            ->assertDontSee('A hidden answered question');

        // Admins see the whole history here too, and hiding removes it for them
        // as well — the admin table is where hidden questions are worked on.
        $this->actingAs($admin)->get(route('creator.answered'))
            ->assertDontSee('A hidden answered question');

        $this->assertNotNull($shown->fresh());
    }

    public function test_a_hidden_question_does_not_count_toward_a_public_answer_count(): void
    {
        $creator = User::factory()->creator()->create();

        Question::factory()->answeredBy($creator)->create();
        $hidden = Question::factory()->answeredBy($creator)->create();
        $hidden->hide(User::factory()->admin()->create());

        $this->get(route('creators.show', $creator))->assertOk();

        $creator->loadCount([
            'answers as answers_count' => fn ($q) => $q->publiclyCredited(),
        ]);

        $this->assertSame(1, $creator->answers_count);
    }

    // ── The claim and answer paths ────────────────────────────────────────

    public function test_a_hidden_question_cannot_be_claimed(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->hidden()->create();

        $this->assertFalse($q->isClaimableBy($creator));
        $this->assertFalse($q->claimBy($creator));

        // …including straight through the claim endpoint.
        $this->actingAs($creator)->post(route('creator.questions.claim', $q));

        $fresh = $q->fresh();
        $this->assertSame(QuestionStatus::Asked, $fresh->status);
        $this->assertNull($fresh->claimed_by);
    }

    public function test_a_question_hidden_mid_write_cannot_be_answered(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($creator)->create();

        $q->hide(User::factory()->admin()->create());

        $this->assertNull($q->publishPrimaryAnswerFrom($creator, 'An answer that must not land.'));
        $this->assertSame(QuestionStatus::Claimed, $q->fresh()->status);
        $this->assertSame(0, $q->fresh()->answers()->count());
    }

    public function test_a_hidden_question_takes_no_alternative_answers(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();

        $q = Question::factory()->answeredBy($main)->create();
        $q->hide(User::factory()->admin()->create());

        $this->assertFalse($q->isAnswerableBy($other));
        $this->assertNull($q->addAlternativeAnswerFrom($other, 'An alternative that must not land.'));
    }

    // ── What the asker sees ───────────────────────────────────────────────

    public function test_the_asker_still_sees_their_hidden_question_with_the_reason(): void
    {
        $asker = User::factory()->create();
        $q     = Question::factory()->hidden(null, 'Please rephrase without naming a specific person.')
            ->create(['asked_by' => $asker->id]);

        $this->actingAs($asker)->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('This question is hidden')
            ->assertSee('Please rephrase without naming a specific person.');
    }

    public function test_my_questions_shows_the_hidden_state_and_the_reason(): void
    {
        $asker = User::factory()->create();
        Question::factory()->hidden(null, 'Off topic for this community.')
            ->create(['asked_by' => $asker->id, 'content' => 'My hidden question']);

        $this->actingAs($asker)->get('/my-questions')
            ->assertOk()
            ->assertSee('My hidden question')
            ->assertSee('Hidden')
            ->assertSee('Off topic for this community.');
    }

    public function test_a_hidden_question_with_no_reason_still_tells_the_asker_it_is_hidden(): void
    {
        $asker = User::factory()->create();
        $q     = Question::factory()->hidden()->create(['asked_by' => $asker->id]);

        $this->actingAs($asker)->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('This question is hidden');
    }

    public function test_an_admin_can_open_any_hidden_question(): void
    {
        $admin = User::factory()->admin()->create();
        $q     = Question::factory()->hidden()->create();

        $this->actingAs($admin)->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('This question is hidden');
    }
}
