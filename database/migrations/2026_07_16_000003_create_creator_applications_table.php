<?php

use App\Enums\ApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_applications', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name');
            $table->text('message');
            $table->enum('status', array_map(fn (ApplicationStatus $s) => $s->value, ApplicationStatus::cases()))
                ->default(ApplicationStatus::Pending->value);
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();

            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_applications');
    }
};