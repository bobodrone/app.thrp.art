<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id'  => Question::factory(),
            'created_by'   => User::factory()->creator(),
            'body'         => "## Answer\n\nYes, the answer is **42**.\n\n- This is markdown\n- More context",
            'published_at' => now()->subMinutes(2),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn () => ['anonymously' => true]);
    }
}
