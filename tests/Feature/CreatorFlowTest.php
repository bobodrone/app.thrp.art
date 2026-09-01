<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Jobs\NotifyAskerOfAnswer;
use App\Livewire\CreatorQuestionDetail;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CreatorFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_dashboard_is_forbidden_for_members(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $this->actingAs($member)->get('/responder')->assertForbidden();
    }

    public function test_creator_dashboard_renders_open_and_my_claimed(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Carl Creator']);
        $asker    = User::factory()->create(['name' => 'Audrey Asker']);

        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'An open question sits here']);
        Question::factory()->claimedBy($creator)->create(['asked_by' => $asker->id, 'content' => 'A claimed one of mine']);

        $response = $this->actingAs($creator)->get('/responder');

        $response->assertStatus(200);
        $response->assertSee('Responder Dashboard');
        $response->assertSee('An open question sits here');
        $response->assertSee('A claimed one of mine');
    }

    public function test_creator_can_claim_open_question_atomically(): void
    {
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q       = Question::factory()->create(['asked_by' => $asker->id]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('claim')
            ->assertHasNoErrors();

        $q->refresh();
        $this->assertSame(QuestionStatus::Claimed->value, $q->status->value);
        $this->assertSame($creator->id, $q->claimed_by);
        $this->assertNotNull($q->claimed_at);
    }

    public function test_second_creator_loses_claim_race(): void
    {
        $asker    = User::factory()->create();
        $q        = Question::factory()->create(['asked_by' => $asker->id]);
        $winner   = User::factory()->creator()->create();
        $loser    = User::factory()->creator()->create();

        // Pre-claim in DB (simulates another creator already having claimed it)
        Question::where('id', $q->id)
            ->where('status', QuestionStatus::Asked)
            ->update([
                'status'     => QuestionStatus::Claimed,
                'claimed_by' => $winner->id,
                'claimed_at' => now(),
            ]);

        Livewire::actingAs($loser)
            ->test(CreatorQuestionDetail::class, ['question' => $q->fresh()])
            ->call('claim')
            ->assertHasErrors(['claim']);

        $q->refresh();
        $this->assertSame($winner->id, $q->claimed_by);
    }

    public function test_claimer_can_submit_answer_atomically_and_dispatches_notification(): void
    {
        Queue::fake();
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q       = Question::factory()->claimedBy($creator)->create(['asked_by' => $asker->id]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', '## Definitely yes

The answer involves **tea** and *patience*. Here is why.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $q->refresh();
        $this->assertSame(QuestionStatus::Answered->value, $q->status->value);
        $this->assertTrue($q->hasVisibleAnswer());
        $this->assertSame($creator->id, $q->primaryAnswer->created_by);
        $this->assertNotNull($q->primaryAnswer->published_at);
        Queue::assertPushed(NotifyAskerOfAnswer::class);
    }

    public function test_reanswering_a_reopened_question_clears_answer_deletion(): void
    {
        Queue::fake();
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        // A question whose previous answer was soft-deleted, then re-claimed.
        $q = Question::factory()
            ->answeredBy($creator, 'Stale removed answer')
            ->create(['asked_by' => $asker->id]);
        $q->removeAnswer($q->primaryAnswer);
        $q->claimBy($creator);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'A brand new answer that is long enough')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $fresh = $q->fresh();
        $this->assertTrue($fresh->hasVisibleAnswer());
        $this->assertSame('A brand new answer that is long enough', $fresh->primaryAnswer->body);
        // The creator's one slot was reused rather than a second row added.
        $this->assertSame(1, $fresh->answers()->count());
    }

    public function test_non_claimer_cannot_answer(): void
    {
        $claimer  = User::factory()->creator()->create();
        $interloper = User::factory()->creator()->create();
        $asker    = User::factory()->create();
        $q        = Question::factory()->claimedBy($claimer)->create(['asked_by' => $asker->id]);

        Livewire::actingAs($interloper)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'Some answer text that is long enough')
            ->call('submitAnswer')
            ->assertHasErrors(['answer']);

        $q->refresh();
        $this->assertSame(QuestionStatus::Claimed->value, $q->status->value);
        $this->assertFalse($q->hasVisibleAnswer());
    }

    public function test_short_answer_is_rejected(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($creator)->create();

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'short')
            ->call('submitAnswer')
            ->assertHasErrors(['answer']);
    }

    public function test_claimer_can_unclaim(): void
    {
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q       = Question::factory()->claimedBy($creator)->create(['asked_by' => $asker->id]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('unclaim');

        $q->refresh();
        $this->assertSame(QuestionStatus::Asked->value, $q->status->value);
        $this->assertNull($q->claimed_by);
        $this->assertNull($q->claimed_at);
    }

    public function test_answerer_can_edit_their_answer_without_renotifying(): void
    {
        Queue::fake();
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q = Question::factory()->answeredBy($creator, 'The first version of the answer')->create([
            'asked_by' => $asker->id,
        ]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertViewHas('canEditAnswer', true)
            ->call('startEditAnswer')
            ->assertSet('editingAnswerId', $q->primary_answer_id)
            ->assertSet('answerDraft', 'The first version of the answer')
            ->set('answerDraft', 'A revised and corrected answer text')
            ->call('updateAnswer')
            ->assertHasNoErrors()
            ->assertSet('editingAnswerId', null);

        $this->assertSame('A revised and corrected answer text', $q->fresh()->primaryAnswer->body);
        Queue::assertNotPushed(NotifyAskerOfAnswer::class);
    }

    public function test_admin_can_edit_another_creators_answer(): void
    {
        $admin   = User::factory()->admin()->create();
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q = Question::factory()->answeredBy($creator, 'Creator wrote this answer')->create([
            'asked_by' => $asker->id,
        ]);

        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertViewHas('canEditAnswer', true)
            ->call('startEditAnswer')
            ->set('answerDraft', 'Admin corrected the answer text')
            ->call('updateAnswer')
            ->assertHasNoErrors();

        $this->assertSame('Admin corrected the answer text', $q->fresh()->primaryAnswer->body);
    }

    public function test_other_creator_cannot_edit_someone_elses_answer(): void
    {
        $author   = User::factory()->creator()->create();
        $intruder = User::factory()->creator()->create();
        $asker    = User::factory()->create();
        $q = Question::factory()->answeredBy($author, 'The protected original answer')->create([
            'asked_by' => $asker->id,
        ]);

        Livewire::actingAs($intruder)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertViewHas('canEditAnswer', false)
            ->set('answerDraft', 'A malicious rewrite attempt here')
            ->call('updateAnswer')
            ->assertHasErrors('answerDraft');

        $this->assertSame('The protected original answer', $q->fresh()->primaryAnswer->body);
    }

    public function test_edited_answer_must_meet_length_rules(): void
    {
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q = Question::factory()->answeredBy($creator, 'A perfectly valid original answer')->create([
            'asked_by' => $asker->id,
        ]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('answerDraft', 'short')
            ->call('updateAnswer')
            ->assertHasErrors('answerDraft');

        $this->assertSame('A perfectly valid original answer', $q->fresh()->primaryAnswer->body);
    }

    public function test_answered_history_lists_only_my_answered(): void
    {
        $me     = User::factory()->creator()->create(['name' => 'Carl Creator']);
        $otherC = User::factory()->creator()->create(['name' => 'Clea Creator']);
        $asker  = User::factory()->create();
        $mine   = Question::factory()->answeredBy($me)->create(['asked_by' => $asker->id, 'content' => 'Mine answered']);
        $theirs = Question::factory()->answeredBy($otherC)->create(['asked_by' => $asker->id, 'content' => 'Theirs answered']);

        $response = $this->actingAs($me)->get('/responder/answered');

        $response->assertStatus(200);
        $response->assertSee('Mine answered');
        $response->assertDontSee('Theirs answered');
    }

    public function test_answered_history_links_the_answerer_to_the_editable_view(): void
    {
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q = Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id]);

        $response = $this->actingAs($creator)->get('/responder/answered');

        $response->assertStatus(200);
        $response->assertSee('Edit response');
        $response->assertSee(route('creator.questions.show', $q));
    }

    public function test_answered_history_shows_admins_every_answer_as_editable(): void
    {
        $admin   = User::factory()->admin()->create();
        $creator = User::factory()->creator()->create(['name' => 'Clea Creator']);
        $asker   = User::factory()->create();
        $q = Question::factory()->answeredBy($creator)->create([
            'asked_by' => $asker->id,
            'content'  => 'Answered by somebody else',
        ]);

        $response = $this->actingAs($admin)->get('/responder/answered');

        $response->assertStatus(200);
        $response->assertSee('Answered by somebody else');
        $response->assertSee('Clea Creator');
        $response->assertSee('Edit response');
        $response->assertSee(route('creator.questions.show', $q));
    }
}
