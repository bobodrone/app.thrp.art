<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Answers move off the questions row into their own table, so one question
     * can carry the claimer's main answer plus alternative takes from other
     * creators. questions.primary_answer_id names the main one; every other
     * answer on the question is an alternative.
     *
     * The legacy answer_* columns are left in place here and dropped by a
     * follow-up migration, so rolling back between deploys cannot lose answers.
     */
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->longText('body');
            $table->string('image_path')->nullable();

            // Snapshotted at publish time, not read live off the user — see the
            // questions.answered_anonymously migration for why.
            $table->boolean('anonymously')->default(false);

            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Hides the answer while keeping it recoverable, replacing the old
            // questions.answer_deleted_at.
            $table->softDeletes();

            // One answer per creator per question: a creator revises their own
            // rather than stacking new ones. Soft-deleted rows keep their slot,
            // so re-answering a reopened question reuses the row.
            $table->unique(['question_id', 'created_by']);

            $table->index(['question_id', 'published_at']);
            $table->index('created_by');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('primary_answer_id')
                ->nullable()
                ->after('claimed_by')
                ->constrained('answers')
                ->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Every existing answer becomes the main answer of its question. The
     * soft-delete state carries over as-is: a question whose answer was removed
     * by an admin keeps pointing at the hidden row, so restoring still works.
     */
    private function backfill(): void
    {
        DB::table('questions')
            ->whereNotNull('answer')
            ->select([
                'id', 'answer', 'answer_image_path', 'answered_by',
                'answered_anonymously', 'answered_at', 'answer_deleted_at', 'updated_at',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($questions) {
                foreach ($questions as $question) {
                    $publishedAt = $question->answered_at ?? $question->updated_at;

                    $answerId = DB::table('answers')->insertGetId([
                        'question_id'  => $question->id,
                        'created_by'   => $question->answered_by,
                        'body'         => $question->answer,
                        'image_path'   => $question->answer_image_path,
                        'anonymously'  => $question->answered_anonymously,
                        'published_at' => $publishedAt,
                        'created_at'   => $publishedAt,
                        'updated_at'   => $question->updated_at,
                        'deleted_at'   => $question->answer_deleted_at,
                    ]);

                    DB::table('questions')
                        ->where('id', $question->id)
                        ->update(['primary_answer_id' => $answerId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Both commands are needed: SQLite cannot drop a column whose
            // inline REFERENCES clause would be left dangling, and pairing the
            // two sends it down the table-rebuild path instead.
            $table->dropForeign(['primary_answer_id']);
            $table->dropColumn('primary_answer_id');
        });

        Schema::dropIfExists('answers');
    }
};
