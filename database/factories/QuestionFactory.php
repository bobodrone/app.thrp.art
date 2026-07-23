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

    public function answeredBy(User $creator): static
    {
        return $this->state(fn () => [
            'status'       => QuestionStatus::Answered,
            'claimed_by'   => $creator->id,
            'answered_by'  => $creator->id,
            'claimed_at'   => now()->subHour(),
            'answered_at'  => now()->subMinutes(2),
            'answer'       => "## Answer\n\nYes, the answer is **42**.\n\n- This is markdown\n- More context",
        ]);
    }
}