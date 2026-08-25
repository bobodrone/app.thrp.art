<?php

namespace Database\Factories;

use App\Enums\QuestionStatus;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'content' => fake()->realText(120),
            'status'  => QuestionStatus::Asked,
            'asked_by' => User::factory(),
        ];
    }

    public function claimedBy(User $creator): static
    {
        return $this->state(fn () => [
            'status'      => QuestionStatus::Claimed,
            'claimed_by'  => $creator->id,
            'claimed_at'  => now()->subMinutes(5),
        ]);
    }

    /**
     * Question with $creator's answer in the main slot.
     */
    public function answeredBy(User $creator, ?string $body = null, array $answer = []): static
    {
        return $this->claimedBy($creator)
            ->state(fn () => ['claimed_at' => now()->subHour()])
            ->afterCreating(function (Question $question) use ($creator, $body, $answer) {
                $question->promoteToPrimary(
                    $question->answers()->create($answer + [
                        'created_by'   => $creator->id,
                        'body'         => $body ?? "## Answer\n\nYes, the answer is **42**.\n\n- This is markdown\n- More context",
                        'anonymously'  => $creator->posts_anonymously,
                        'published_at' => now()->subMinutes(2),
                    ]),
                );
            });
    }

    /**
     * Taken out of public view by a moderator. Goes through hide() rather than
     * setting the columns, so the state cannot drift from the real action.
     */
    public function hidden(?User $admin = null, ?string $reason = null): static
    {
        return $this->afterCreating(function (Question $question) use ($admin, $reason) {
            $question->hide($admin ?? User::factory()->admin()->create(), $reason);
        });
    }

    /**
     * Adds an alternative answer from another creator, on top of whatever the
     * main answer is.
     */
    public function withAlternativeFrom(User $creator, ?string $body = null, array $answer = []): static
    {
        return $this->afterCreating(function (Question $question) use ($creator, $body, $answer) {
            $question->answers()->create($answer + [
                'created_by'   => $creator->id,
                'body'         => $body ?? 'A different way to look at it: try the other approach instead.',
                'anonymously'  => $creator->posts_anonymously,
                'published_at' => now()->subMinute(),
            ]);
        });
    }
}
