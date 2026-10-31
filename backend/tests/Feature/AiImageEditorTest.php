<?php
namespace Tests\Feature;

use App\Models\ProviderConfiguration;
use App\Services\AiImageEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AiImageEditorTest extends TestCase
{
    use RefreshDatabase;

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        return $bytes;
    }

    private function provider(): void
    {
        ProviderConfiguration::create([
            'type' => 'openai-compatible',
            'name' => 'test',
            'endpoint' => 'https://api.example.com/v1',
            'model' => 'img-1',
            'api_key' => 'secret',
        ]);
    }

    public function test_returns_png_from_provider_on_success(): void
    {
        $this->provider();
        $png = $this->pngBytes();
        Http::fake(['https://api.example.com/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode($png)]]])]);

        $result = app(AiImageEditor::class)->render($this->pngBytes(), 'buat latar kayu');

        $this->assertSame('image/png', $result->mime);
        $this->assertSame('png', $result->ext);
        $this->assertSame($png, $result->bytes);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret')
            && $request->url() === 'https://api.example.com/v1/images/edits');
    }

    public function test_forwards_valid_size_and_drops_invalid(): void
    {
        $this->provider();
        $png = $this->pngBytes();
        Http::fake(['*' => Http::response(['data' => [['b64_json' => base64_encode($png)]]])]);

        app(AiImageEditor::class)->render($this->pngBytes(), 'edit', '1024x1024');
        Http::assertSent(fn ($request) => collect($request->data())->contains(fn ($part) => $part['name'] === 'size' && $part['contents'] === '1024x1024'));

        app(AiImageEditor::class)->render($this->pngBytes(), 'edit', 'evil-9999');
        Http::assertSent(fn ($request) => ! collect($request->data())->contains(fn ($part) => $part['name'] === 'size'));
    }

    public function test_without_prompt_falls_back_to_identity_and_sends_no_request(): void
    {
        $this->provider();
        Http::fake();

        $result = app(AiImageEditor::class)->render($this->pngBytes(), null);

        $this->assertSame('image/png', $result->mime);
        Http::assertNothingSent();
    }

    public function test_throws_when_provider_fails(): void
    {
        $this->provider();
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->expectException(RuntimeException::class);
        app(AiImageEditor::class)->render($this->pngBytes(), 'edit');
    }

    public function test_throws_when_no_provider_configured(): void
    {
        Http::fake();
        $this->expectException(RuntimeException::class);
        app(AiImageEditor::class)->render($this->pngBytes(), 'edit');
    }
}
