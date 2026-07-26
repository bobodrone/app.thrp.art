<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminQuestionsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_is_forbidden_from_admin_questions(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/admin/questions')->assertForbidden();
    }

    public function test_creator_is_forbidden_from_admin_questions(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)->get('/admin/questions')->assertForbidden();
    }

    public function test_admin_sees_all_questions(): void
    {
        $admin  = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $asker  = User::factory()->create(['name' => 'Audrey Asker']);
        $creator = User::factory()->creator()->create(['name' => 'Carl Creator']);
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Open question']);
        Question::factory()->claimedBy($creator)->create(['asked_by' => $asker->id, 'content' => 'Claimed question']);
        Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id, 'content' => 'Answered question']);

        $response = $this->actingAs($admin)->get('/admin/questions');

        $response->assertStatus(200);
        $response->assertSee('All Questions');
        $response->assertSee('Open question');
        $response->assertSee('Claimed question');
        $response->assertSee('Answered question');
        $response->assertSee('Audrey Asker');
        $response->assertSee('Carl Creator');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/questions')->assertRedirect('/login');
    }

    public function test_status_filter_only_shows_matching_questions(): void
    {
        $admin  = User::factory()->admin()->create();
        $asker  = User::factory()->create();
        $creator = User::factory()->creator()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'An asked one here']);
        Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id, 'content' => 'An answered one here']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('statusFilter', 'answered')
            ->assertSee('An answered one here')
            ->assertDontSee('An asked one here');
    }

    public function test_invalid_status_filter_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Visible question']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('statusFilter', 'bogus')
            ->assertSee('Visible question');
    }

    public function test_search_filters_content_case_insensitively(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'How to bake sourdough bread']);
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Best hiking trails in Japan']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('search', 'sourdough')
            ->assertSee('How to bake sourdough bread')
            ->assertDontSee('Best hiking trails in Japan');
    }

    public function test_reset_filters_clears_status_and_search(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        $creator = User::factory()->creator()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Open question']);
        Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id, 'content' => 'Answered question']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('statusFilter', 'answered')
            ->set('search', 'nonexistent')
            ->assertDontSee('Open question')
            ->call('resetFilters')
            ->assertSet('statusFilter', '')
            ->assertSet('search', '')
            ->assertSee('Open question')
            ->assertSee('Answered question');
    }

    public function test_url_sync_carries_filter_state(): void
    {
        $admin  = User::factory()->admin()->create();
        $asker  = User::factory()->create();
        $creator = User::factory()->creator()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Open question']);
        Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id, 'content' => 'Answered question']);

        $response = $this->actingAs($admin)->call('GET', '/admin/questions', ['status' => 'answered', 'q' => 'answered']);

        $response->assertStatus(200);
        $response->assertSee('Answered question');
        $response->assertDontSee('Open question');
    }

    public function test_pagination_preserves_filters(): void
    {
        $admin  = User::factory()->admin()->create();
        $asker  = User::factory()->create();
        // 3 answered questions → page=1 with 100/page only has 1 page
        Question::factory()->answeredBy(User::factory()->creator()->create())->create(['asked_by' => $asker->id, 'content' => 'Answered A']);
        Question::factory()->answeredBy(User::factory()->creator()->create())->create(['asked_by' => $asker->id, 'content' => 'Answered B']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('statusFilter', 'answered')
            ->assertSee('Answered A')
            ->assertSee('Answered B');
    }

    public function test_empty_state_shows_when_no_matches(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Visible question']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('search', 'zzznomatchzzz')
            ->assertSee('No questions match your filters.');
    }

    public function test_admin_can_edit_question_content(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        $q = Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Original content here']);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->call('edit', $q->id)
            ->assertSet('editContent', 'Original content here')
            ->assertSet('showEdit', true)
            ->set('editContent', 'Updated content that is long enough')
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSet('showEdit', false);

        $this->assertSame('Updated content that is long enough', $q->fresh()->content);
    }

    public function test_edit_validates_question_length(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        $q = Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Original content here']);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->call('edit', $q->id)
            ->set('editContent', 'short')
            ->call('saveEdit')
            ->assertHasErrors('editContent');

        $this->assertSame('Original content here', $q->fresh()->content);
    }

    public function test_admin_can_edit_answer_of_answered_question(): void
    {
        $admin   = User::factory()->admin()->create();
        $asker   = User::factory()->create();
        $creator = User::factory()->creator()->create();
        $q = Question::factory()->answeredBy($creator, 'The original answer text')->create([
            'asked_by' => $asker->id,
            'content'  => 'A question here now',
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->call('edit', $q->id)
            ->assertSet('editHasAnswer', true)
            ->set('editAnswer', 'A corrected answer that is long enough')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $this->assertSame('A corrected answer that is long enough', $q->fresh()->primaryAnswer->body);
    }

    public function test_deleting_answer_soft_deletes_and_reopens_question(): void
    {
        $admin   = User::factory()->admin()->create();
        $asker   = User::factory()->create();
        $creator = User::factory()->creator()->create();
        $q = Question::factory()->answeredBy($creator, 'The original answer text')->create([
            'asked_by' => $asker->id,
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->call('deleteAnswer', $q->id);

        $fresh = $q->fresh();
        $this->assertSame(QuestionStatus::Asked, $fresh->status);
        $this->assertTrue($fresh->hasHiddenAnswer());
        $this->assertNull($fresh->claimed_by);

        // The row is retained for recovery…
        $hidden = $fresh->primaryAnswer()->withTrashed()->first();
        $this->assertSame('The original answer text', $hidden->body);
        $this->assertSame($creator->id, $hidden->created_by);
        // …but is no longer considered a visible answer.
        $this->assertFalse($fresh->hasVisibleAnswer());
    }

    public function test_soft_deleted_answer_is_hidden_on_public_question_page(): void
    {
        $asker   = User::factory()->create();
        $creator = User::factory()->creator()->create();
        $q = Question::factory()->answeredBy($creator, 'Secret answer body here')->create([
            'asked_by' => $asker->id,
        ]);
        $q->removeAnswer($q->primaryAnswer);

        $this->get(route('questions.show', $q))
            ->assertStatus(200)
            ->assertDontSee('Secret answer body here');
    }

    public function test_deleting_question_soft_deletes_and_hides_it(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        $q = Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Doomed question here']);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->call('delete', $q->id)
            ->assertDontSee('Doomed question here');

        $this->assertSoftDeleted('questions', ['id' => $q->id]);
    }

    public function test_show_deleted_toggle_reveals_trashed_questions(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        $q = Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Trashed question here']);
        $q->delete();

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->assertDontSee('Trashed question here')
            ->set('showDeleted', true)
            ->assertSee('Trashed question here')
            ->assertSee('Deleted');
    }

    public function test_admin_can_restore_trashed_question(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        $q = Question::factory()->create(['asked_by' => $asker->id]);
        $q->delete();

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('showDeleted', true)
            ->call('restore', $q->id);

        $this->assertNotSoftDeleted('questions', ['id' => $q->id]);
    }

    public function test_admin_can_permanently_delete_question(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        $q = Question::factory()->create(['asked_by' => $asker->id]);
        $q->delete();

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('showDeleted', true)
            ->call('forceDelete', $q->id);

        $this->assertDatabaseMissing('questions', ['id' => $q->id]);
    }

    public function test_admin_can_restore_a_soft_deleted_answer(): void
    {
        $admin   = User::factory()->admin()->create();
        $asker   = User::factory()->create();
        $creator = User::factory()->creator()->create();
        $q = Question::factory()->answeredBy($creator, 'The original answer text')->create([
            'asked_by' => $asker->id,
        ]);
        // Simulate a soft-deleted answer (reopened question).
        $q->removeAnswer($q->primaryAnswer);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->call('restoreAnswer', $q->id);

        $fresh = $q->fresh();
        $this->assertSame(QuestionStatus::Answered, $fresh->status);
        $this->assertFalse($fresh->hasHiddenAnswer());
        $this->assertTrue($fresh->hasVisibleAnswer());
    }

    public function test_table_shows_relative_users_and_formatted_dates(): void
    {
        $admin   = User::factory()->admin()->create();
        $asker   = User::factory()->create(['name' => 'Audrey']);
        $claimer = User::factory()->creator()->create(['name' => 'Carl']);
        $answerer = User::factory()->creator()->create(['name' => 'Clea']);
        $q = Question::factory()
            ->answeredBy($answerer)
            ->create(['asked_by' => $asker->id, 'content' => 'A detailed q', 'created_at' => now()]);
        // The claim and the answer belong to different creators here.
        $q->update(['claimed_by' => $claimer->id]);

        $response = $this->actingAs($admin)->get('/admin/questions');

        $response->assertSee('Audrey');
        $response->assertSee('Carl');
        $response->assertSee('Clea');
        $response->assertSee(format_date($q->fresh()->created_at));
    }
}