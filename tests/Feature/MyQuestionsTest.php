<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
