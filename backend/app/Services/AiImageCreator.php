<?php
namespace App\Services;

use App\Models\ProviderConfiguration;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Generate an image from a text prompt via an OpenAI-compatible provider
 * (POST /images/generations). Uses the provider's `model_create`. Bounded
 * timeout; fails closed on any transport/provider error.
 */
final class AiImageCreator
{
    private const CONNECT_TIMEOUT_SEC = 5;
    private const TOTAL_TIMEOUT_SEC = 60;
    private const ALLOWED_MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    private const ALLOWED_SIZES = ['512x512', '1024x1024'];

    public function __construct(private readonly HttpFactory $http) {}

    public function create(string $prompt, ?string $size = null): EditedImage
    {
        if (trim($prompt) === '') {
            throw new RuntimeException('A prompt is required to create an image.');
        }
        if ($size !== null && ! in_array($size, self::ALLOWED_SIZES, true)) {
            $size = null;
        }

        $provider = ProviderConfiguration::query()
            ->whereIn('type', ['openai-compatible', 'other'])
            ->orderByDesc('updated_at')
            ->first();
        if (! $provider || ! is_string($provider->endpoint) || $provider->endpoint === '') {
            throw new RuntimeException('No AI provider is configured.');
        }
        $model = (string) ($provider->model_create ?? '');
        if ($model === '') {
            throw new RuntimeException('No image-create model is configured.');
        }

        $response = $this->http
            ->withToken((string) ($provider->api_key ?? ''))
            ->acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SEC)
            ->timeout(self::TOTAL_TIMEOUT_SEC)
            ->post(rtrim($provider->endpoint, '/').'/images/generations', array_filter([
                'model' => $model,
                'prompt' => $prompt,
                'size' => $size,
                'response_format' => 'b64_json',
                'n' => 1,
            ]));

        if (! $response->successful()) {
            throw new RuntimeException('AI provider request failed.');
        }
        $encoded = $response->json('data.0.b64_json');
        if (! is_string($encoded) || $encoded === '') {
            throw new RuntimeException('AI provider returned no image.');
        }
        $bytes = base64_decode($encoded, true);
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('AI provider returned invalid image data.');
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! isset(self::ALLOWED_MIME[$info['mime']])) {
            throw new RuntimeException('AI provider returned an unsupported image type.');
        }
        return new EditedImage($bytes, $info['mime'], self::ALLOWED_MIME[$info['mime']]);
    }
}
