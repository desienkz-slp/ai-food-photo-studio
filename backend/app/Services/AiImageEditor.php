<?php
namespace App\Services;

use App\Models\ProviderConfiguration;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * AI-backed image editor for OpenAI-compatible providers. Every job starts from
 * an uploaded asset, so this uses images/edits (image-to-image) when a prompt
 * is present; without a prompt it returns the identity result unchanged.
 *
 * Fails closed: any provider/transport error throws so the caller marks the job
 * failed and never commits unverified output.
 */
final class AiImageEditor implements ImageEditor
{
    private const CONNECT_TIMEOUT_SEC = 5;
    private const TOTAL_TIMEOUT_SEC = 60;
    private const ALLOWED_MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    private const ALLOWED_SIZES = ['512x512', '1024x1024'];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly LocalIdentityCompositor $fallback,
    ) {}

    public function render(string $original, ?string $prompt = null, ?string $size = null): EditedImage
    {
        // No instruction: nothing for the model to do. Preserve the image.
        if ($prompt === null || trim($prompt) === '') {
            return $this->fallback->render($original, $prompt, $size);
        }
        // Ignore an unrecognized size rather than fail the whole edit.
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

        $endpoint = rtrim($provider->endpoint, '/');
        $token = (string) ($provider->api_key ?? '');
        $model = (string) ($provider->model ?? '');

        $response = $this->http
            ->withToken($token)
            ->acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SEC)
            ->timeout(self::TOTAL_TIMEOUT_SEC)
            ->attach('image', $original, 'image.png', ['Content-Type' => 'image/png'])
            ->asMultipart()
            ->post($endpoint.'/images/edits', array_filter([
                'model' => $model !== '' ? $model : null,
                'prompt' => $prompt,
                'size' => $size,
                'response_format' => 'b64_json',
                'n' => '1',
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
        $mime = $info['mime'];

        return new EditedImage($bytes, $mime, self::ALLOWED_MIME[$mime]);
    }
}
