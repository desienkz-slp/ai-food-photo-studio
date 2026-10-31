<?php
namespace App\Services;

use App\Models\ProviderConfiguration;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Analyze an image via an OpenAI-compatible multimodal chat endpoint
 * (POST /chat/completions with an image_url data URI). Uses the provider's
 * `model_vision`. Bounded timeout; fails closed on any error.
 */
final class AiVisionAnalyzer
{
    private const CONNECT_TIMEOUT_SEC = 5;
    private const TOTAL_TIMEOUT_SEC = 60;

    public function __construct(private readonly HttpFactory $http) {}

    public function analyze(string $image, string $mime, ?string $prompt = null): string
    {
        $instruction = trim((string) $prompt) !== ''
            ? $prompt
            : 'Describe this food photo and suggest concrete improvements for appetizing presentation.';

        $provider = ProviderConfiguration::query()
            ->whereIn('type', ['openai-compatible', 'other'])
            ->orderByDesc('updated_at')
            ->first();
        if (! $provider || ! is_string($provider->endpoint) || $provider->endpoint === '') {
            throw new RuntimeException('No AI provider is configured.');
        }
        $model = (string) ($provider->model_vision ?? '');
        if ($model === '') {
            throw new RuntimeException('No vision model is configured.');
        }

        $dataUri = 'data:'.$mime.';base64,'.base64_encode($image);
        $response = $this->http
            ->withToken((string) ($provider->api_key ?? ''))
            ->acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SEC)
            ->timeout(self::TOTAL_TIMEOUT_SEC)
            ->post(rtrim($provider->endpoint, '/').'/chat/completions', [
                'model' => $model,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $instruction],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                    ],
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI provider request failed.');
        }
        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('AI provider returned no analysis.');
        }
        return $text;
    }
}
