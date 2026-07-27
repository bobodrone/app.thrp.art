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

    public function test_claim_banner_hides_an_anonymous_creators_name(): void
    {
        $creator = User::factory()->creator()->create([
            'name' => 'Ada Gardener', 'posts_anonymously' => true,
        ]);
        $q = Question::factory()->claimedBy($creator)->create();

        // The window between claiming and publishing is public too.
        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee(Answer::ANONYMOUS_AUTHOR)
            ->assertDontSee('Ada Gardener');
    }

    public function test_claim_banner_still_names_a_creator_who_is_not_anonymous(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);
        $q       = Question::factory()->claimedBy($creator)->create();

        $this->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('Ada Gardener');
    }

    public function test_claim_banner_shows_the_real_name_to_admins(): void
    {
        $creator = User::factory()->creator()->create([
            'name' => 'Ada Gardener', 'posts_anonymously' => true,
        ]);
        $q = Question::factory()->claimedBy($creator)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee('Ada Gardener');
    }

    public function test_an_anonymous_alternative_answer_is_marked_on_the_creator_page(): void
    {
        $main = User::factory()->creator()->create();
        $alt  = User::factory()->creator()->create([
            'name' => 'Ada Gardener', 'posts_anonymously' => true,
        ]);

        $q = Question::factory()
            ->answeredBy($main)
            ->withAlternativeFrom($alt)
            ->create();

        // The creator who wrote it needs to see that it went out anonymously.
        Livewire::actingAs($alt)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->assertSee(Answer::ANONYMOUS_AUTHOR)
            ->assertSee('Posted anonymously');
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
