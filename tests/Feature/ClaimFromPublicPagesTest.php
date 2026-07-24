<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimFromPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_sees_claim_button_on_question_page(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->create(['asked_by' => User::factory()->create()->id]);

        $this->actingAs($creator)->get(route('questions.show', $q))
            ->assertStatus(200)
            ->assertSee(route('creator.questions.claim', $q));
    }

    public function test_member_and_guest_do_not_see_claim_button(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $q      = Question::factory()->create(['asked_by' => $member->id]);

        $this->actingAs($member)->get(route('questions.show', $q))
            ->assertStatus(200)
            ->assertDontSee(route('creator.questions.claim', $q));

        $this->get(route('questions.show', $q))
            ->assertStatus(200)
            ->assertDontSee(route('creator.questions.claim', $q));

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee(route('creator.questions.claim', $q));
    }

    public function test_creator_sees_claim_button_on_home_feed_card(): void
    {
        $creator  = User::factory()->creator()->create();
        $open     = Question::factory()->create(['asked_by' => User::factory()->create()->id]);
        $answered = Question::factory()->answeredBy($creator)->create(['asked_by' => User::factory()->create()->id]);

        $response = $this->actingAs($creator)->get('/');

        $response->assertStatus(200);
        $response->assertSee(route('creator.questions.claim', $open));
        // Already answered — nothing left to claim.
        $response->assertDontSee(route('creator.questions.claim', $answered));
    }

    public function test_claimer_gets_an_answer_link_back_on_both_pages(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($creator)->create(['asked_by' => User::factory()->create()->id]);

        $this->actingAs($creator)->get(route('questions.show', $q))
            ->assertStatus(200)
            ->assertSee('Answer →', false)
            ->assertSee(route('creator.questions.show', $q));

        $this->actingAs($creator)->get('/')
            ->assertStatus(200)
            ->assertSee(route('creator.questions.show', $q));
    }

    public function test_other_creators_get_no_link_into_someone_elses_claim(): void
    {
        $claimer = User::factory()->creator()->create(['name' => 'Carla Claimer']);
        $other   = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($claimer)->create(['asked_by' => User::factory()->create()->id]);

        $response = $this->actingAs($other)->get(route('questions.show', $q));
        $response->assertStatus(200);
        $response->assertSee('Being answered by');
        $response->assertDontSee(route('creator.questions.show', $q));
        $response->assertDontSee(route('creator.questions.claim', $q));

        $this->actingAs($other)->get('/')
            ->assertStatus(200)
            ->assertDontSee(route('creator.questions.show', $q));
    }

    public function test_creator_can_claim_via_post_and_lands_on_creator_view(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->create(['asked_by' => User::factory()->create()->id]);

        $this->actingAs($creator)
            ->post(route('creator.questions.claim', $q))
            ->assertRedirect(route('creator.questions.show', $q));

        $q->refresh();
        $this->assertSame(QuestionStatus::Claimed->value, $q->status->value);
        $this->assertSame($creator->id, $q->claimed_by);
        $this->assertNotNull($q->claimed_at);
    }

    public function test_claiming_an_already_claimed_question_flashes_an_error(): void
    {
        $winner = User::factory()->creator()->create();
        $loser  = User::factory()->creator()->create();
        $q      = Question::factory()->claimedBy($winner)->create(['asked_by' => User::factory()->create()->id]);

        $this->actingAs($loser)
            ->post(route('creator.questions.claim', $q))
            ->assertRedirect(route('creator.questions.show', $q))
            ->assertSessionHas('claim_error');

        $this->assertSame($winner->id, $q->fresh()->claimed_by);
    }

    public function test_member_cannot_claim_via_post(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $q      = Question::factory()->create(['asked_by' => $member->id]);

        $this->actingAs($member)->post(route('creator.questions.claim', $q))->assertForbidden();

        $this->assertSame(QuestionStatus::Asked->value, $q->fresh()->status->value);
    }

    public function test_guest_cannot_claim_via_post(): void
    {
        $q = Question::factory()->create(['asked_by' => User::factory()->create()->id]);

        $this->post(route('creator.questions.claim', $q))->assertRedirect(route('login'));

        $this->assertSame(QuestionStatus::Asked->value, $q->fresh()->status->value);
    }
}
