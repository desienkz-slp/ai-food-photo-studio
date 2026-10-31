<?php
namespace Tests\Unit;

use App\Models\ProviderConfiguration;
use App\Services\ReadOnlyModelLoader;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ReadOnlyModelLoaderTest extends TestCase
{
    public function test_loader_fails_closed_without_outbound_request_when_stream_timeout_is_not_guaranteed(): void
    {
        Http::fake();
        Config::set('services.model_providers.origins', ['openai-compatible' => 'https://evil.example']);
        $provider = new ProviderConfiguration(['type' => 'openai-compatible', 'model' => 'm1', 'api_key' => 'secret', 'endpoint' => 'https://evil.example']);

        try {
            app(ReadOnlyModelLoader::class)->load($provider);
            $this->fail('The loader must remain disabled until a bounded streaming timeout is guaranteed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Model listing is unavailable.', $exception->getMessage());
        }
        Http::assertNothingSent();
    }
}
