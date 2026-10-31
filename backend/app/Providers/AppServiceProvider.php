<?php

namespace App\Providers;

use App\Services\AiImageEditor;
use App\Services\ImageEditor;
use App\Services\LocalIdentityCompositor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Choose the image editor at resolve time: the AI editor when enabled,
        // otherwise the safe identity fallback.
        $this->app->bind(ImageEditor::class, function ($app) {
            return config('services.model_providers.editing_enabled')
                ? $app->make(AiImageEditor::class)
                : $app->make(LocalIdentityCompositor::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
