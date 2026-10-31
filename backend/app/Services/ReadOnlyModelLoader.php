<?php
namespace App\Services;

use App\Models\ProviderConfiguration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ReadOnlyModelLoader
{
    // Bounded network budget so a slow/hostile provider can never hang a request.
    private const CONNECT_TIMEOUT_SEC = 4;
    private const TOTAL_TIMEOUT_SEC = 8;

    /**
     * Fetch the provider's model list from its OpenAI-compatible /models
     * endpoint. Fails closed if no endpoint is configured.
     *
     * @return list<string>
     */
    public function load(ProviderConfiguration $provider): array
    {
        $endpoint = rtrim((string) $provider->endpoint, '/');
        if ($endpoint === '') {
            throw new RuntimeException('Provider endpoint is not configured.');
        }

        $token = (string) ($provider->api_key ?? '');

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SEC)
            ->timeout(self::TOTAL_TIMEOUT_SEC)
            ->get($endpoint.'/models');

        if (! $response->successful()) {
            throw new RuntimeException('Model listing request failed.');
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new RuntimeException('Unexpected model listing response.');
        }

        $models = [];
        foreach ($data as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            if (is_string($id) && $id !== '') {
                $models[] = $id;
            }
        }
        sort($models);

        return array_values(array_unique($models));
    }
}
