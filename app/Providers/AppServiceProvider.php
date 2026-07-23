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
        //
    }
}