<?php

namespace Database\Seeders;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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

        // Answered questions, covering every shape the answer block can take:
        // main answer alone, with an image, with an alternative from the other
        // creator, and an alternative that carries its own image.
        Question::factory()
            ->answeredBy($creatorB, <<<'MD'
                Water it **twice a week** and keep it out of the wind. The soil should
                feel damp a knuckle deep — any wetter and the roots start to rot.

                - Morning watering beats evening watering
                - Mulch holds the moisture in on hot days
                MD, ['image_path' => $this->answerImage('seed-watering.png', 'Watering can', [26, 92, 56])])
            ->withAlternativeFrom($creatorA, <<<'MD'
                I'd go the other way on this one. Twice a week is a good starting point,
                but let the plant tell you: lift the pot, and if it still feels heavy,
                skip a day. Fixed schedules drown more seedlings than they save.
                MD)
            ->create();

        Question::factory()
            ->answeredBy($creatorA, <<<'MD'
                ## Short answer

                Yes — but wait until after the first frost. Cutting back too early
                leaves the crown exposed right when it needs the insulation most.
                MD)
            ->withAlternativeFrom($creatorB, <<<'MD'
                Worth adding: if you are in a mild region this matters much less. I have
                cut mine back in early autumn for years without losing a single plant.
                MD)
            ->create();

        Question::factory()
            ->answeredBy($creatorA, <<<'MD'
                The yellowing usually starts at the *lower* leaves and works upward —
                that pattern points at nitrogen, not overwatering. A diluted feed every
                other week through the growing season fixes it.
                MD, ['image_path' => $this->answerImage('seed-leaves.png', 'Yellowing leaves', [138, 108, 22])])
            ->create();

        Question::factory()
            ->answeredBy($creatorB, <<<'MD'
                Full sun, and more of it than you think. Six hours is the minimum; eight
                is where they actually start to fruit properly.
                MD)
            ->create();

        Question::factory()
            ->answeredBy($creatorA, <<<'MD'
                Sow them straight into the ground once the soil warms up. They resent
                being transplanted and will sulk for a fortnight if you start them off
                in trays.
                MD)
            ->withAlternativeFrom($creatorB, <<<'MD'
                A counterpoint from someone with a short season: I start mine indoors in
                biodegradable pots and plant the whole pot out. The roots are never
                disturbed, and I gain three weeks.
                MD, ['image_path' => $this->answerImage('seed-pots.png', 'Seedling pots', [92, 46, 26])])
            ->create();

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

    /**
     * A generated placeholder image on the uploads disk, so seeded answers
     * render a real picture instead of a broken one. Filenames are fixed rather
     * than random: re-seeding replaces these instead of piling up orphans.
     *
     * @param  array{int, int, int}  $rgb  background colour
     * @return string  path relative to the disk, for answers.image_path
     */
    private function answerImage(string $filename, string $label, array $rgb): string
    {
        $config = config('uploads.answer_image');
        $path   = $config['directory'].'/'.$filename;

        $image = imagecreatetruecolor(800, 500);
        imagefilledrectangle($image, 0, 0, 800, 500, imagecolorallocate($image, ...$rgb));

        // GD's built-in font only — a TTF would have to exist on whatever
        // machine runs the seeder.
        $white = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 30, 240, $label, $white);

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk($config['disk'])->put($path, $contents);

        return $path;
    }
}