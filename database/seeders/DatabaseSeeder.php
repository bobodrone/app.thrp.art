<?php

namespace Database\Seeders;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1 admin (so /admin/setup no longer activates)
        $admin = User::factory()->create([
            'name'  => 'Ada Admin',
            'email' => 'admin@example.com',
            'role'  => UserRole::Admin,
        ]);

        // 2 creators
        $creatorA = User::factory()->create([
            'name'  => 'Carl Creator',
            'email' => 'creator@example.com',
            'role'  => UserRole::Creator,
        ]);
        $creatorB = User::factory()->create([
            'name'  => 'Clea Creator',
            'email' => 'creator2@example.com',
            'role'  => UserRole::Creator,
        ]);

        // 3 members
        $memberA = User::factory()->create(['name' => 'Mia Member', 'email' => 'member@example.com']);
        User::factory()->create(['name' => 'Mike Member', 'email' => 'mike@example.com']);
        User::factory()->create(['name' => 'Mel Member', 'email' => 'mel@example.com']);

        // ~10 questions spread across statuses
        Question::factory()->count(4)->create(['asked_by' => $memberA->id]);
        Question::factory()->claimedBy($creatorA)->create();
        Question::factory()->claimedBy($creatorA)->create(['asked_by' => $admin->id]);
        Question::factory()->answeredBy($creatorB)->create();
        Question::factory()->answeredBy($creatorA)->create();
        Question::factory()->answeredBy($creatorA)->create();
        Question::factory()->answeredBy($creatorB)->create();
        Question::factory()->answeredBy($creatorA)->create();

        // 3 creator applications across different statuses
        \App\Models\CreatorApplication::create([
            'email'   => 'applicant@example.com',
            'name'    => 'Annie Applicant',
            'message' => 'I love answering questions about tea and philosophy and would like to help out on the platform.',
            'status'  => \App\Enums\ApplicationStatus::Pending,
            'applied_at' => now()->subDays(2),
        ]);
        \App\Models\CreatorApplication::create([
            'email'   => 'applicant2@example.com',
            'name'    => 'Alex Applicant',
            'message' => 'Another pending application — I have experience running Q&A communities and would like to contribute.',
            'status'  => \App\Enums\ApplicationStatus::Pending,
            'applied_at' => now()->subDays(1),
        ]);
        \App\Models\CreatorApplication::create([
            'email'   => 'approved@example.com',
            'name'    => 'April Applicant',
            'message' => 'I would love to join as a creator to share my knowledge of gardening.',
            'status'  => \App\Enums\ApplicationStatus::Approved,
            'applied_at' => now()->subDays(10),
            'reviewed_at' => now()->subDays(8),
        ]);
    }
}