<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Jobs\NotifyAskerOfAnswer;
use App\Jobs\NotifyCreatorsOfNewQuestion;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HomeAndQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_question_feed(): void
    {
        $member  = User::factory()->create();
        $creator = User::factory()->creator()->create();
        Question::factory()->answeredBy($creator)->create(['asked_by' => $member->id]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('The Human Response Project');
        $response->assertSee('Real questions.');
    }

    public function test_guest_cannot_submit_question(): void
    {
        $response = $this->post('/', ['content' => 'What is the meaning of life, the universe, and everything?']);

        $response->assertRedirect('/login');
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_member_can_submit_question_and_is_redirected_to_detail(): void
    {
        Queue::fake();
        $member = User::factory()->create();

        $response = $this->actingAs($member)->post('/', [
            'content' => 'What is the meaning of life, the universe, and everything?',
        ]);

        $this->assertDatabaseCount('questions', 1);
        $q = Question::first();
        $this->assertSame(QuestionStatus::Asked->value, $q->status->value);
        $this->assertSame($member->id, $q->asked_by);

        $response->assertRedirect(route('questions.show', $q));
        Queue::assertPushed(NotifyCreatorsOfNewQuestion::class);
    }

    public function test_question_must_be_10_to_2000_chars(): void
    {
        $member = User::factory()->create();

        $tooShort = $this->from('/')->actingAs($member)->post('/', ['content' => 'too short']);
        $tooShort->assertSessionHasErrors(['content']);
        $this->assertDatabaseCount('questions', 0);

        $tooLong = $this->from('/')->actingAs($member)->post('/', ['content' => str_repeat('a', 2001)]);
        $tooLong->assertSessionHasErrors(['content']);
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_question_detail_renders_for_anyone(): void
    {
        $asker   = User::factory()->create(['name' => 'Audrey Asker']);
        $creator = User::factory()->creator()->create(['name' => 'Carl Creator']);
        $q       = Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id]);

        $response = $this->get(route('questions.show', $q));

        $response->assertStatus(200);
        $response->assertSee($q->content);
        $response->assertSee('Audrey Asker');
        $response->assertSee('Carl Creator');
        $response->assertSee('<h2>Answer</h2>', false); // rendered markdown fixture
    }

    public function test_unknown_question_returns_404(): void
    {
        $response = $this->get('/questions/9999');

        $response->assertNotFound();
    }
}

class MyQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_questions_page_renders_only_mine(): void
    {
        $me  = User::factory()->create(['name' => 'Me']);
        $you = User::factory()->create(['name' => 'You']);
        Question::factory()->create(['asked_by' => $me->id, 'content' => 'My own question here']);
        Question::factory()->create(['asked_by' => $you->id, 'content' => 'Your question not mine']);

        $response = $this->actingAs($me)->get('/my-questions');

        $response->assertStatus(200);
        $response->assertSee('My own question here');
        $response->assertDontSee('Your question not mine');
    }

    public function test_unmarked_question_on_my_questions_page_links_to_detail(): void
    {
        $me = User::factory()->create();
        $q  = Question::factory()->create(['asked_by' => $me->id, 'content' => 'A question of mine']);

        $response = $this->actingAs($me)->get('/my-questions');

        $response->assertSee(route('questions.show', $q));
    }
}

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

        $response = $this->actingAs($creator)->post("/creator/questions/{$q->id}/claim");

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

        Livewire::test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q->fresh()])
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

        Livewire::test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
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

    public function test_non_claimer_cannot_answer(): void
    {
        $claimer  = User::factory()->creator()->create();
        $interloper = User::factory()->creator()->create();
        $asker    = User::factory()->create();
        $q        = Question::factory()->claimedBy($claimer)->create(['asked_by' => $asker->id]);

        Livewire::test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
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

        Livewire::test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'short')
            ->call('submitAnswer')
            ->assertHasErrors(['answer']);
    }

    public function test_claimer_can_unclaim(): void
    {
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q       = Question::factory()->claimedBy($creator)->create(['asked_by' => $asker->id]);

        Livewire::test(\App\Livewire\CreatorQuestionDetail::class, ['question' => $q])
            ->call('unclaim');

        $q->refresh();
        $this->assertSame(QuestionStatus::Asked->value, $q->status->value);
        $this->assertNull($q->claimed_by);
        $this->assertNull($q->claimed_at);
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