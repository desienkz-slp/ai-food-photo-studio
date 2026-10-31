<?php
namespace Tests\Unit;

use App\Models\ProviderConfiguration;
use App\Services\ReadOnlyModelLoader;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ReadOnlyModelLoaderTest extends TestCase
{
    public function test_loader_fails_closed_without_endpoint_and_sends_no_request(): void
    {
        Http::fake();
        $provider = new ProviderConfiguration(['type' => 'openai-compatible', 'model' => 'm1']);

        try {
            app(ReadOnlyModelLoader::class)->load($provider);
            $this->fail('The loader must fail closed when no endpoint is configured.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Provider endpoint is not configured.', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_loader_returns_sorted_unique_model_ids_from_provider(): void
    {
        Http::fake([
            'https://api.example.com/v1/models' => Http::response(['data' => [
                ['id' => 'gpt-b'], ['id' => 'gpt-a'], ['id' => 'gpt-b'], ['no-id' => true],
            ]]),
        ]);
        $provider = new ProviderConfiguration(['type' => 'openai-compatible', 'model' => 'm1', 'endpoint' => 'https://api.example.com/v1']);
        $provider->api_key = 'secret-token';

        $models = app(ReadOnlyModelLoader::class)->load($provider);

        $this->assertSame(['gpt-a', 'gpt-b'], $models);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request->url() === 'https://api.example.com/v1/models');
    }

    public function test_loader_throws_on_unsuccessful_response(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);
        $provider = new ProviderConfiguration(['type' => 'other', 'model' => 'm', 'endpoint' => 'https://api.example.com/v1']);
        $provider->api_key = 'k';

        $this->expectException(RuntimeException::class);
        app(ReadOnlyModelLoader::class)->load($provider);
    }
}
