<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Moderation, kept off the `status` axis on purpose: a question that
            // is asked/claimed/answered is still all of those while hidden, and
            // unhiding has to put it back exactly where it was.
            $table->timestamp('hidden_at')->nullable()->after('deleted_at');

            $table->foreignId('hidden_by')
                ->nullable()
                ->after('hidden_at')
                ->constrained('users')
                ->nullOnDelete();

            // Shown to the asker, so it is written for them rather than for the
            // admin log. Optional — a question can be hidden without a word.
            $table->text('hidden_reason')->nullable()->after('hidden_by');

            $table->index('hidden_at');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['hidden_by']);
            $table->dropIndex(['hidden_at']);
            $table->dropColumn(['hidden_at', 'hidden_by', 'hidden_reason']);
        });
    }
};
