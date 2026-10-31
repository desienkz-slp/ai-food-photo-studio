<?php
namespace App\Services;

/** Immutable result of an image edit/generation: encoded image bytes + type. */
final class EditedImage
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $mime,
        public readonly string $ext,
    ) {}
}
