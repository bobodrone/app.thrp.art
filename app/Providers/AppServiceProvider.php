<?php

namespace App\Providers;

use App\Services\MarkdownRenderer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MarkdownRenderer::class);
    }

    public function boot(): void
    {
        // Livewire caps temporary uploads at 12 MB by default, before our own
        // rules ever run. Keep that ceiling in step with the .env limit.
        config()->set('livewire.temporary_file_upload.rules', [
            'required', 'file', 'max:'.config('uploads.answer_image.max_kb'),
        ]);
    }
}
