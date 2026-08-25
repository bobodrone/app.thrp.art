<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Moderation, kept off the `role` axis on purpose — the same shape
            // hiding takes on questions. A blocked responder is still a
            // responder, and unblocking has to put them back exactly where
            // they were rather than reconstruct a role from memory.
            $table->timestamp('blocked_at')->nullable()->after('posts_anonymously');

            $table->foreignId('blocked_by')
                ->nullable()
                ->after('blocked_at')
                ->constrained('users')
                ->nullOnDelete();

            // Read by the blocked person on the sign-in screen, so it is
            // written for them rather than for the admin log. Optional.
            $table->text('blocked_reason')->nullable()->after('blocked_by');

            // Set when the account's personal details have been scrubbed. The
            // row and everything it authored stay; only the person goes.
            $table->timestamp('anonymised_at')->nullable()->after('blocked_reason');

            $table->index('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['blocked_by']);
            $table->dropIndex(['blocked_at']);
            $table->dropColumn(['blocked_at', 'blocked_by', 'blocked_reason', 'anonymised_at']);
        });
    }
};
