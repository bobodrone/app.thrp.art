<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\CreatorQuestionDetail;
use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnswererLinkTest extends TestCase
{
    use RefreshDatabase;

    private function answered(User $creator, bool $anonymously = false): Question
    {
        return Question::factory()
            ->answeredBy(
                $creator,
                'Water it twice a week and keep it out of the wind.',
                ['anonymously' => $anonymously],
            )
            ->create(['asked_by' => User::factory()->create()->id]);
    }

    public function test_question_page_links_the_answerer_to_their_profile(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);
        $q       = $this->answered($creator);

        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('Ada Gardener')
            ->assertSee(route('creators.show', $creator));
    }

    public function test_home_feed_links_the_answerer_to_their_profile(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);
        $this->answered($creator);

        $this->get('/')
            ->assertOk()
            ->assertSee(route('creators.show', $creator));
    }

    public function test_an_anonymous_answer_is_never_linked(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);
        $q       = $this->answered($creator, anonymously: true);

        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee(Answer::ANONYMOUS_AUTHOR)
            ->assertDontSee('Ada Gardener')
            ->assertDontSee(route('creators.show', $creator));
    }

    public function test_an_anonymous_answer_is_not_linked_for_admins_either(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);
        $q       = $this->answered($creator, anonymously: true);

        // The admin still sees who wrote it — but the href would put that id
        // into markup that is otherwise identical to the public one.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('Ada Gardener')
            ->assertDontSee(route('creators.show', $creator));
    }

    public function test_a_demoted_answerer_is_not_linked(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);
        $q       = $this->answered($creator);
        $creator->update(['role' => UserRole::Member]);

        // Their profile page 404s now, so the credit must not link to it.
        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('Ada Gardener')
            ->assertDontSee(route('creators.show', $creator));
    }

    public function test_creator_view_links_the_answerer(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);
        $q       = $this->answered($creator);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertSee(route('creators.show', $creator), escape: false);
    }

    public function test_admin_questions_table_links_the_answerer(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);
        $this->answered($creator);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->assertSee(route('creators.show', $creator), escape: false);
    }
}
