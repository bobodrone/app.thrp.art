<?php

use App\Enums\QuestionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->longText('content');

            $table->enum('status', array_map(fn (QuestionStatus $s) => $s->value, QuestionStatus::cases()))
                ->default(QuestionStatus::Asked->value);

            $table->foreignId('asked_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('claimed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('answered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->longText('answer')->nullable();

            $table->timestamps();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('answered_at')->nullable();

            $table->index('status');
            $table->index('claimed_by');
            $table->index('answered_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
