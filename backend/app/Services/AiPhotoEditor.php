<?php
namespace App\Services;

use App\Models\ProviderConfiguration;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * ChatGPT-style photo editing: send an input image plus a text instruction to
 * an OpenAI-compatible multimodal chat endpoint (POST /chat/completions) using
 * an image-generation-capable model, and read back a newly generated image.
 *
 * Stateless: nothing is persisted. The provider returns the edited image either
 * as a markdown data URI in the message content, or in a non-standard `images`
 * field; both shapes are handled. Bounded timeout; fails closed on any error.
 */
final class AiPhotoEditor
{
    private const CONNECT_TIMEOUT_SEC = 5;
    private const TOTAL_TIMEOUT_SEC = 90;
    private const ALLOWED_MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(private readonly HttpFactory $http) {}

    public function edit(string $image, string $mime, string $instruction): EditedImage
    {
        if (trim($instruction) === '') {
            throw new RuntimeException('An instruction is required to edit the photo.');
        }

        $provider = ProviderConfiguration::query()
            ->whereIn('type', ['openai-compatible', 'other'])
            ->orderByDesc('updated_at')
            ->first();
        if (! $provider || ! is_string($provider->endpoint) || $provider->endpoint === '') {
            throw new RuntimeException('No AI provider is configured.');
        }
        // The editing model must be able to output images. We reuse `model`
        // (the image-edit slot) for this ChatGPT-style flow.
        $model = (string) ($provider->model ?? '');
        if ($model === '') {
            throw new RuntimeException('No image-edit model is configured.');
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

        $bytes = $this->extractImage($response->json());
        if ($bytes === null) {
            throw new RuntimeException('AI provider returned no edited image.');
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! isset(self::ALLOWED_MIME[$info['mime']])) {
            throw new RuntimeException('AI provider returned an unsupported image type.');
        }
        return new EditedImage($bytes, $info['mime'], self::ALLOWED_MIME[$info['mime']]);
    }

    /**
     * Pull base64 image bytes from the provider response. Supports:
     *  - message.content markdown: ![..](data:image/...;base64,XXXX)
     *  - message.content as array of parts with image_url data URIs
     *  - message.images[].image_url.url data URIs (non-standard but common)
     *
     * @param  array<mixed>|null  $json
     */
    private function extractImage(?array $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }
        $message = $json['choices'][0]['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        // 1) images[] array (non-standard)
        foreach ((array) ($message['images'] ?? []) as $img) {
            $url = is_array($img) ? ($img['image_url']['url'] ?? $img['url'] ?? null) : null;
            $bytes = is_string($url) ? $this->decodeDataUri($url) : null;
            if ($bytes !== null) {
                return $bytes;
            }
        }

        // 2) content as array of parts
        $content = $message['content'] ?? null;
        if (is_array($content)) {
            foreach ($content as $part) {
                $url = is_array($part) ? ($part['image_url']['url'] ?? null) : null;
                $bytes = is_string($url) ? $this->decodeDataUri($url) : null;
                if ($bytes !== null) {
                    return $bytes;
                }
            }
        }

        // 3) content string with a markdown data URI
        if (is_string($content) && preg_match('#data:image/[a-zA-Z.+-]+;base64,([A-Za-z0-9+/=]+)#', $content, $m) === 1) {
            return $this->decodeBase64($m[1]);
        }

        return null;
    }

    private function decodeDataUri(string $url): ?string
    {
        if (preg_match('#^data:image/[a-zA-Z.+-]+;base64,(.+)$#s', $url, $m) !== 1) {
            return null;
        }
        return $this->decodeBase64($m[1]);
    }

    private function decodeBase64(string $encoded): ?string
    {
        $bytes = base64_decode($encoded, true);
        return (is_string($bytes) && $bytes !== '') ? $bytes : null;
    }
}
