<?php

namespace Tests\Feature;

use App\Jobs\NotifyAskerOfAnswer;
use App\Livewire\CreatorQuestionDetail;
use App\Mail\AnswerNotification;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Editing an answer stays silent. The responder can opt out of that silence,
 * one edit at a time, with the checkbox on the edit form.
 */
class NotifyAskerOfEditTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $asker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->creator()->create();
        $this->asker   = User::factory()->create();
    }

    private function answered(string $body = 'The first version of the answer.'): Question
    {
        return Question::factory()
            ->answeredBy($this->creator, $body)
            ->create(['asked_by' => $this->asker->id]);
    }

    // --- the default: silence ----------------------------------------------

    public function test_an_edit_sends_nothing_when_the_box_is_left_unticked(): void
    {
        Queue::fake();
        $q          = $this->answered();
        $publishedAt = $q->primaryAnswer->published_at;

        Livewire::actingAs($this->creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->assertSet('notifyAskerOfEdit', false)
            ->set('answerDraft', 'A quietly corrected answer text.')
            ->call('updateAnswer')
            ->assertHasNoErrors();

        $answer = $q->fresh()->primaryAnswer;

        $this->assertSame('A quietly corrected answer text.', $answer->body);
        // The answer keeps its original timestamp — a silent edit, as before.
        $this->assertEquals($publishedAt, $answer->published_at);
        Queue::assertNotPushed(NotifyAskerOfAnswer::class);
    }

    // --- opting in ----------------------------------------------------------

    public function test_ticking_the_box_notifies_the_asker(): void
    {
        Queue::fake();
        $q = $this->answered();

        Livewire::actingAs($this->creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('answerDraft', 'A substantially rewritten answer text.')
            ->set('notifyAskerOfEdit', true)
            ->call('updateAnswer')
            ->assertHasNoErrors();

        Queue::assertPushed(
            NotifyAskerOfAnswer::class,
            fn (NotifyAskerOfAnswer $job) => $job->question->id === $q->id && $job->edited === true,
        );
    }

    public function test_the_asker_gets_mail_written_for_an_edit_not_a_first_answer(): void
    {
        Mail::fake();
        $q = $this->answered();

        // The job runs inline so the mailable itself is built and rendered.
        Livewire::actingAs($this->creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('answerDraft', 'A substantially rewritten answer text.')
            ->set('notifyAskerOfEdit', true)
            ->call('updateAnswer')
            ->assertHasNoErrors();

        Mail::assertSent(AnswerNotification::class, function (AnswerNotification $mail) use ($q) {
            $rendered = $mail->render();

            $this->assertTrue($mail->hasTo($this->asker->email));
            $this->assertTrue($mail->edited);
            $this->assertSame(
                'A response to your question has been updated — THRP',
                $mail->envelope()->subject,
            );
            // The edit wording, and none of the first-answer wording.
            $this->assertStringContainsString('A response to your question has been updated', $rendered);
            $this->assertStringNotContainsString('Someone has responded to your question!', $rendered);
            // And it still points at the question it is about.
            $this->assertStringContainsString(route('questions.show', $q->id), $rendered);

            return true;
        });
    }

    public function test_an_admin_editing_someone_elses_answer_can_notify_too(): void
    {
        Queue::fake();
        $q = $this->answered();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('answerDraft', 'An admin corrected a factual error here.')
            ->set('notifyAskerOfEdit', true)
            ->call('updateAnswer')
            ->assertHasNoErrors();

        Queue::assertPushed(
            NotifyAskerOfAnswer::class,
            fn (NotifyAskerOfAnswer $job) => $job->edited === true,
        );
    }

    public function test_an_alternative_answer_can_be_edited_with_a_notification(): void
    {
        Queue::fake();
        $other = User::factory()->creator()->create();
        $q     = $this->answered();
        $alt   = $q->addAlternativeAnswerFrom($other, 'An alternative take on the question.');

        Livewire::actingAs($other)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer', $alt->id)
            ->assertSet('notifyAskerOfEdit', false)
            ->set('answerDraft', 'A revised alternative take on the question.')
            ->set('notifyAskerOfEdit', true)
            ->call('updateAnswer')
            ->assertHasNoErrors();

        Queue::assertPushed(
            NotifyAskerOfAnswer::class,
            fn (NotifyAskerOfAnswer $job) => $job->edited === true,
        );
    }

    // --- the flag must never leak across edits ------------------------------

    public function test_cancelling_clears_the_notify_flag(): void
    {
        $q = $this->answered();

        Livewire::actingAs($this->creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('notifyAskerOfEdit', true)
            ->call('cancelEditAnswer')
            ->assertSet('notifyAskerOfEdit', false);
    }

    public function test_opening_another_answer_clears_the_notify_flag(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->creator()->create();
        $q     = $this->answered();
        $alt   = $q->addAlternativeAnswerFrom($other, 'An alternative take on the question.');

        // An admin can open either answer, so the flag has a chance to leak.
        Livewire::actingAs($admin)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('notifyAskerOfEdit', true)
            ->call('startEditAnswer', $alt->id)
            ->assertSet('editingAnswerId', $alt->id)
            ->assertSet('notifyAskerOfEdit', false);
    }

    public function test_a_successful_save_clears_the_notify_flag(): void
    {
        Queue::fake();
        $q = $this->answered();

        Livewire::actingAs($this->creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('answerDraft', 'A substantially rewritten answer text.')
            ->set('notifyAskerOfEdit', true)
            ->call('updateAnswer')
            ->assertHasNoErrors()
            ->assertSet('notifyAskerOfEdit', false);
    }

    // --- a rejected save must not mail --------------------------------------

    public function test_a_save_rejected_by_validation_sends_nothing(): void
    {
        Queue::fake();
        $q = $this->answered();

        Livewire::actingAs($this->creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('answerDraft', 'too short')
            ->set('notifyAskerOfEdit', true)
            ->call('updateAnswer')
            ->assertHasErrors('answerDraft');

        Queue::assertNotPushed(NotifyAskerOfAnswer::class);
        $this->assertSame('The first version of the answer.', $q->fresh()->primaryAnswer->body);
    }

    public function test_a_save_rejected_by_the_ownership_check_sends_nothing(): void
    {
        Queue::fake();
        $q       = $this->answered();
        $creator = $this->creator;
        $other   = User::factory()->creator()->create();

        // The author opens the form, then the answer stops being theirs to edit.
        $component = Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('answerDraft', 'An edit that should never land or mail.')
            ->set('notifyAskerOfEdit', true);

        $q->primaryAnswer->update(['created_by' => $other->id]);

        $component->call('updateAnswer')->assertHasErrors('answerDraft');

        Queue::assertNotPushed(NotifyAskerOfAnswer::class);
        $this->assertSame('The first version of the answer.', $q->fresh()->primaryAnswer->body);
    }

    // --- nobody left to tell -------------------------------------------------

    /**
     * `questions.asked_by` is NOT NULL and cascades on delete, so a stored
     * question always has an asker — deleting one takes the question with it,
     * and anonymising keeps the row. The job's guard is therefore a backstop
     * rather than a path the UI can reach, and is exercised as one here.
     */
    public function test_no_mail_is_attempted_when_the_question_has_no_asker(): void
    {
        Mail::fake();
        $q = $this->answered();
        $q->setRelation('asker', null);

        (new NotifyAskerOfAnswer($q, edited: true))->handle();

        Mail::assertNothingSent();
    }

    public function test_the_notify_checkbox_is_offered_on_the_edit_form(): void
    {
        $q = $this->answered();

        Livewire::actingAs($this->creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->assertSee('Notify the person who asked this question about this edit');
    }

    // --- a first answer still reads as a first answer ------------------------

    public function test_a_first_answer_notification_is_unchanged(): void
    {
        Mail::fake();
        $q = Question::factory()
            ->claimedBy($this->creator)
            ->create(['asked_by' => $this->asker->id]);

        Livewire::actingAs($this->creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'A brand new answer to this question.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        (new NotifyAskerOfAnswer($q->fresh()))->handle();

        Mail::assertSent(AnswerNotification::class, function (AnswerNotification $mail) {
            return $mail->edited === false
                && $mail->envelope()->subject === 'Your question has a response — THRP'
                && str_contains($mail->render(), 'Someone has responded to your question!');
        });
    }
}
