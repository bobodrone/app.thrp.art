<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Soft-delete the whole question.
            $table->softDeletes();

            // Soft-delete just the answer (the answer lives on the question row),
            // keeping answer/answered_by/answered_at for recovery.
            $table->timestamp('answer_deleted_at')->nullable()->after('answered_at');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('answer_deleted_at');
        });
    }
};
