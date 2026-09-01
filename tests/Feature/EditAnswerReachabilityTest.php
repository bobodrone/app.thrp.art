<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Getting back to a published answer to edit it. The edit form itself lives on
 * the responder view and never expires; what is covered here is whether a
 * responder can find their way to it once the page they answered on is gone.
 */
class EditAnswerReachabilityTest extends TestCase
{
    use RefreshDatabase;

    private function answered(User $creator, string $body = 'The main answer body here.'): Question
    {
        return Question::factory()
            ->answeredBy($creator, $body)
            ->create(['asked_by' => User::factory()->create()->id]);
    }

    // --- /responder/answered ------------------------------------------------

    public function test_a_responder_whose_answer_is_an_alternative_is_offered_the_edit_form(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();

        $q = $this->answered($main);
        $q->addAlternativeAnswerFrom($other, 'A different way to look at it entirely.');

        // The bug this covers: the list used to ask whether the *main* answer
        // was theirs, so an alternative got a read-only "View" link instead.
        $this->actingAs($other)
            ->get(route('creator.answered'))
            ->assertOk()
            ->assertSee('Edit response →')
            ->assertDontSee('View →')
            ->assertSee(route('creator.questions.show', $q));
    }

    public function test_the_edit_form_is_reachable_from_the_link_that_list_gives(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();

        $q = $this->answered($main);
        $q->addAlternativeAnswerFrom($other, 'A different way to look at it entirely.');

        $this->actingAs($other)
            ->get(route('creator.questions.show', $q))
            ->assertOk()
            ->assertSee('Edit response');
    }

    public function test_a_responder_still_gets_the_edit_form_for_their_main_answer(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = $this->answered($creator);

        $this->actingAs($creator)
            ->get(route('creator.answered'))
            ->assertOk()
            ->assertSee('Edit response →')
            ->assertSee(route('creator.questions.show', $q));
    }

    // --- navigation ---------------------------------------------------------

    public function test_the_answered_list_is_in_the_navigation_for_a_responder(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('creator.answered'))
            ->assertSee('My Responses');
    }

    public function test_the_answered_list_is_in_the_navigation_for_an_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('creator.answered'));
    }

    public function test_a_member_gets_no_answered_list_in_the_navigation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('creator.answered'));
    }

    // --- the public question page -------------------------------------------

    public function test_the_author_is_offered_the_edit_form_from_the_public_page(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = $this->answered($creator);

        $this->actingAs($creator)
            ->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('Edit your response')
            ->assertSee(route('creator.questions.show', $q));
    }

    public function test_the_author_of_an_alternative_is_offered_the_edit_form_from_the_public_page(): void
    {
        $main  = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();

        $q = $this->answered($main);
        $q->addAlternativeAnswerFrom($other, 'A different way to look at it entirely.');

        $this->actingAs($other)
            ->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('Edit your response')
            ->assertSee(route('creator.questions.show', $q));
    }

    public function test_an_admin_is_offered_the_edit_form_on_someone_elses_answer(): void
    {
        $q = $this->answered(User::factory()->creator()->create());

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('Edit response')
            ->assertSee(route('creator.questions.show', $q));
    }

    public function test_a_guest_is_offered_no_edit_form_on_the_public_page(): void
    {
        $q = $this->answered(User::factory()->creator()->create());

        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertDontSee('Edit your response')
            ->assertDontSee('Edit response')
            ->assertDontSee(route('creator.questions.show', $q));
    }

    public function test_the_asker_is_offered_no_edit_form_on_their_own_question(): void
    {
        $asker = User::factory()->create();
        $q     = Question::factory()
            ->answeredBy(User::factory()->creator()->create())
            ->create(['asked_by' => $asker->id]);

        $this->actingAs($asker)
            ->get(route('questions.show', $q))
            ->assertOk()
            ->assertDontSee('Edit your response')
            ->assertDontSee('Edit response')
            ->assertDontSee(route('creator.questions.show', $q));
    }

    /**
     * They are still sent to the responder view — that is where an alternative
     * is written — so the URL is on the page. What they must not get is an
     * invitation to edit an answer that is not theirs.
     */
    public function test_a_responder_who_did_not_answer_is_offered_no_edit_form(): void
    {
        $q = $this->answered(User::factory()->creator()->create());

        $this->actingAs(User::factory()->creator()->create())
            ->get(route('questions.show', $q))
            ->assertOk()
            ->assertDontSee('Edit your response')
            ->assertDontSee('Edit response')
            ->assertSee('Add your response');
    }

    /**
     * Ownership alone does not open the form: it sits behind the responder-role
     * gate, so an author demoted to member would only be sent to a 403.
     */
    public function test_an_author_demoted_to_member_is_offered_no_edit_form(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = $this->answered($creator);

        $creator->update(['role' => UserRole::Member]);

        $this->actingAs($creator->fresh())
            ->get(route('questions.show', $q))
            ->assertOk()
            ->assertDontSee('Edit your response')
            ->assertDontSee(route('creator.questions.show', $q));
    }
}
