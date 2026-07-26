<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('role');
            $table->text('bio')->nullable()->after('avatar_path');
            // [{"label": "Instagram", "url": "https://…"}, …]
            $table->json('social_links')->nullable()->after('bio');
            $table->boolean('posts_anonymously')->default(false)->after('social_links');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'bio', 'social_links', 'posts_anonymously']);
        });
    }
};
