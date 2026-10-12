<?php

namespace Tests\Feature\Ai;

use App\Ai\Embeddings\EmbeddingProvider;
use App\Ai\Embeddings\GeminiEmbeddingProvider;
use App\Ai\Exceptions\EmbeddingException;
use App\Ai\Services\EmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'ai.embeddings.cache' => true,
            'ai.embeddings.dimensions' => 768,
        ]);
    }

    protected function fakeProvider(int $dimensions = 3): EmbeddingProvider
    {
        return new class($dimensions) implements EmbeddingProvider
        {
            public int $calls = 0;

            public function __construct(private int $dimensions) {}

            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'fake-model';
            }

            public function dimensions(): int
            {
                return $this->dimensions;
            }

            public function embed(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array
            {
                $this->calls++;

                return array_fill(0, $this->dimensions, 0.5);
            }

            public function embedBatch(array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array
            {
                $this->calls += count($texts);

                return array_map(fn () => array_fill(0, $this->dimensions, 0.5), array_values($texts));
            }
        };
    }

    public function test_generates_real_vector_and_caches_it(): void
    {
        $provider = $this->fakeProvider();
        $service = new EmbeddingService($provider);

        $first = $service->generateEmbedding('hello world');
        $second = $service->generateEmbedding('hello world');

        $this->assertSame([0.5, 0.5, 0.5], $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $provider->calls, 'Second call must be served from cache.');
    }

    public function test_cache_is_isolated_per_tenant(): void
    {
        $provider = $this->fakeProvider();

        $tenantA = new class($provider) extends EmbeddingService
        {
            public string $scope = 'tenant-a';

            public function tenantKey(): string
            {
                return $this->scope;
            }
        };

        $tenantB = new class($provider) extends EmbeddingService
        {
            public string $scope = 'tenant-b';

            public function tenantKey(): string
            {
                return $this->scope;
            }
        };

        $tenantA->generateEmbedding('shared text');
        $tenantA->generateEmbedding('shared text');
        $tenantB->generateEmbedding('shared text');

        $this->assertSame(2, $provider->calls, 'Each tenant must resolve its own vector.');
    }

    public function test_empty_text_is_rejected(): void
    {
        $service = new EmbeddingService($this->fakeProvider());

        $this->expectException(EmbeddingException::class);

        $service->generateEmbedding('   ');
    }

    public function test_provider_failure_propagates_and_is_not_fabricated(): void
    {
        $failing = new class implements EmbeddingProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'fake-model';
            }

            public function dimensions(): int
            {
                return 3;
            }

            public function embed(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array
            {
                throw EmbeddingException::requestFailed('fake', 'boom');
            }

            public function embedBatch(array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array
            {
                throw EmbeddingException::requestFailed('fake', 'boom');
            }
        };

        $service = new EmbeddingService($failing);

        $this->expectException(EmbeddingException::class);

        $service->generateEmbedding('hello');
    }

    public function test_batch_generates_and_caches_each_chunk(): void
    {
        $provider = $this->fakeProvider();
        $service = new EmbeddingService($provider);

        $first = $service->batchGenerateEmbeddings(['a', 'b']);
        $second = $service->batchGenerateEmbeddings(['a', 'b']);

        $this->assertCount(2, $first);
        $this->assertSame(2, $provider->calls, 'Second batch must be served from cache.');
        $this->assertSame($first, $second);
    }

    public function test_cosine_similarity(): void
    {
        $service = new EmbeddingService($this->fakeProvider());

        $this->assertEqualsWithDelta(1.0, $service->cosineSimilarity([1, 0], [1, 0]), 0.0001);
        $this->assertSame(0.0, $service->cosineSimilarity([1, 0], [0, 1]));
        $this->assertSame(0.0, $service->cosineSimilarity([1, 0, 0], [1, 0]));
        $this->assertSame(0.0, $service->cosineSimilarity([], [1]));
    }

    public function test_gemini_provider_returns_real_values(): void
    {
        config(['ai.providers.gemini.key' => 'test-key', 'ai.embeddings.dimensions' => 4]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => [0.1, 0.2, 0.3, 0.4]],
            ]),
        ]);

        $vector = (new GeminiEmbeddingProvider)->embed('hello', 'RETRIEVAL_QUERY');

        $this->assertSame([0.1, 0.2, 0.3, 0.4], $vector);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'embedContent'));
    }

    public function test_gemini_provider_throws_on_http_error(): void
    {
        config(['ai.providers.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('nope', 500),
        ]);

        $this->expectException(EmbeddingException::class);

        (new GeminiEmbeddingProvider)->embed('hello');
    }

    public function test_gemini_provider_throws_when_unconfigured(): void
    {
        config(['ai.providers.gemini.key' => '']);

        Http::fake();

        $this->expectException(EmbeddingException::class);

        (new GeminiEmbeddingProvider)->embed('hello');

        Http::assertNothingSent();
    }

    public function test_gemini_provider_rejects_wrong_dimension(): void
    {
        config(['ai.providers.gemini.key' => 'test-key', 'ai.embeddings.dimensions' => 768]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => [0.1, 0.2, 0.3]],
            ]),
        ]);

        $this->expectException(EmbeddingException::class);

        (new GeminiEmbeddingProvider)->embed('hello');
    }
}
