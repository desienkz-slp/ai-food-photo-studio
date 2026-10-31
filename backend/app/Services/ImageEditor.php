<?php
namespace App\Services;

interface ImageEditor
{
    /**
     * Render an edited/generated image from the original bytes and an optional
     * prompt. Implementations must enforce a bounded time budget and must throw
     * on any failure (never return unverified output).
     */
    public function render(string $original, ?string $prompt = null): EditedImage;
}
