<?php
namespace App\Services;

use App\Models\ProviderConfiguration;
use RuntimeException;

final class ReadOnlyModelLoader
{
    /** @return array<string, mixed> */
    public function load(ProviderConfiguration $provider): array
    {
        // Disabled until the transport can enforce a deadline while streaming.
        throw new RuntimeException('Model listing is unavailable.');
    }
}
