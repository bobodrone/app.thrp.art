<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The creator's anonymity preference is snapshotted onto the answer rather
     * than read live from the user, so turning the preference off later cannot
     * retroactively unmask answers that were posted anonymously.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->boolean('answered_anonymously')->default(false)->after('answered_by');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('answered_anonymously');
        });
    }
};
