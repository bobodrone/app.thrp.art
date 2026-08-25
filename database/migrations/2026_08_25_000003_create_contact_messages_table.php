<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();

            // Set when a logged-in user writes in. Nulled rather than cascaded
            // on delete: the message still needs reading after the account it
            // came from is gone, and the name/email columns keep the context.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');

            // Hashed, not raw: enough to spot one address flooding the form,
            // without keeping an identifiable IP for every visitor who writes.
            $table->string('ip_hash', 64)->nullable();

            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The inbox lists unhandled first, newest first.
            $table->index(['handled_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
