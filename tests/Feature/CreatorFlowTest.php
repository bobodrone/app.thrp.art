<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Jobs\NotifyAskerOfAnswer;
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
        $this->actingAs($member)->get('/creator')->assertForbidden();
    }

    public function test_creator_dashboard_renders_open_and_my_claimed(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Carl Creator']);
        $asker    = User::factory()->create(['name' => 'Audrey Asker']);

        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'An open question sits here']);
        Question::factory()->claimedBy($creator)->create(['asked_by' => $asker->id, 'content' => 'A claimed one of mine']);

        $response = $this->actingAs($creator)->get('/creator');

        $response->assertStatus(200);
        $response->assertSee('Creator Dashboard');
        $response->assertSee('An open question sits here');
        $response->assertSee('A claimed one of mine');
    }

    public function test_creator_can_claim_open_question_atomically(): void
    {
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q       = Question::factory()->create(['asked_by' => $asker->id]);

        Livewire::actingAs($creator)
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
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
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q->fresh()])
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
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', '## Definitely yes

The answer involves **tea** and *patience*. Here is why.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $q->refresh();
        $this->assertSame(QuestionStatus::Answered->value, $q->status->value);
        $this->assertNotNull($q->answer);
        $this->assertSame($creator->id, $q->answered_by);
        $this->assertNotNull($q->answered_at);
        Queue::assertPushed(NotifyAskerOfAnswer::class);
    }

    public function test_reanswering_a_reopened_question_clears_answer_deletion(): void
    {
        Queue::fake();
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        // A question whose previous answer was soft-deleted, then re-claimed.
        $q = Question::factory()->claimedBy($creator)->create([
            'asked_by'          => $asker->id,
            'answer'            => 'Stale removed answer',
            'answer_deleted_at' => now(),
        ]);

        Livewire::actingAs($creator)
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'A brand new answer that is long enough')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $fresh = $q->fresh();
        $this->assertNull($fresh->answer_deleted_at);
        $this->assertTrue($fresh->hasVisibleAnswer());
        $this->assertSame('A brand new answer that is long enough', $fresh->answer);
    }

    public function test_non_claimer_cannot_answer(): void
    {
        $claimer  = User::factory()->creator()->create();
        $interloper = User::factory()->creator()->create();
        $asker    = User::factory()->create();
        $q        = Question::factory()->claimedBy($claimer)->create(['asked_by' => $asker->id]);

        Livewire::actingAs($interloper)
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'Some answer text that is long enough')
            ->call('submitAnswer')
            ->assertHasErrors(['answer']);

        $q->refresh();
        $this->assertSame(QuestionStatus::Claimed->value, $q->status->value);
        $this->assertNull($q->answer);
    }

    public function test_short_answer_is_rejected(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($creator)->create();

        Livewire::actingAs($creator)
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
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
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
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
        $q = Question::factory()->answeredBy($creator)->create([
            'asked_by' => $asker->id,
            'answer'   => 'The first version of the answer',
        ]);

        Livewire::actingAs($creator)
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
            ->assertViewHas('canEditAnswer', true)
            ->call('startEditAnswer')
            ->assertSet('editingAnswer', true)
            ->assertSet('answerDraft', 'The first version of the answer')
            ->set('answerDraft', 'A revised and corrected answer text')
            ->call('updateAnswer')
            ->assertHasNoErrors()
            ->assertSet('editingAnswer', false);

        $this->assertSame('A revised and corrected answer text', $q->fresh()->answer);
        Queue::assertNotPushed(NotifyAskerOfAnswer::class);
    }

    public function test_admin_can_edit_another_creators_answer(): void
    {
        $admin   = User::factory()->admin()->create();
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q = Question::factory()->answeredBy($creator)->create([
            'asked_by' => $asker->id,
            'answer'   => 'Creator wrote this answer',
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
            ->assertViewHas('canEditAnswer', true)
            ->call('startEditAnswer')
            ->set('answerDraft', 'Admin corrected the answer text')
            ->call('updateAnswer')
            ->assertHasNoErrors();

        $this->assertSame('Admin corrected the answer text', $q->fresh()->answer);
    }

    public function test_other_creator_cannot_edit_someone_elses_answer(): void
    {
        $author   = User::factory()->creator()->create();
        $intruder = User::factory()->creator()->create();
        $asker    = User::factory()->create();
        $q = Question::factory()->answeredBy($author)->create([
            'asked_by' => $asker->id,
            'answer'   => 'The protected original answer',
        ]);

        Livewire::actingAs($intruder)
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
            ->assertViewHas('canEditAnswer', false)
            ->set('answerDraft', 'A malicious rewrite attempt here')
            ->call('updateAnswer')
            ->assertHasErrors('answerDraft');

        $this->assertSame('The protected original answer', $q->fresh()->answer);
    }

    public function test_edited_answer_must_meet_length_rules(): void
    {
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q = Question::factory()->answeredBy($creator)->create([
            'asked_by' => $asker->id,
            'answer'   => 'A perfectly valid original answer',
        ]);

        Livewire::actingAs($creator)
            ->test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('answerDraft', 'short')
            ->call('updateAnswer')
            ->assertHasErrors('answerDraft');

        $this->assertSame('A perfectly valid original answer', $q->fresh()->answer);
    }

    public function test_answered_history_lists_only_my_answered(): void
    {
        $me     = User::factory()->creator()->create(['name' => 'Carl Creator']);
        $otherC = User::factory()->creator()->create(['name' => 'Clea Creator']);
        $asker  = User::factory()->create();
        $mine   = Question::factory()->answeredBy($me)->create(['asked_by' => $asker->id, 'content' => 'Mine answered']);
        $theirs = Question::factory()->answeredBy($otherC)->create(['asked_by' => $asker->id, 'content' => 'Theirs answered']);

        $response = $this->actingAs($me)->get('/creator/answered');

        $response->assertStatus(200);
        $response->assertSee('Mine answered');
        $response->assertDontSee('Theirs answered');
    }
}
