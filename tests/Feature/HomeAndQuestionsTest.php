<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
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
