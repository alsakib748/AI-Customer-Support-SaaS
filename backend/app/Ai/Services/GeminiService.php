<?php
// app/Ai/Services/GeminiService.php

namespace App\Ai\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $model;
    protected array $safetySettings;
    protected array $generationConfig;
    protected int $maxRetries;

    public function __construct()
    {
        $this->apiKey = (string) config('ai.providers.gemini.key', '');
        $this->model = config('ai.providers.gemini.model', 'gemini-3.6-flash');
        $this->safetySettings = config('ai.gemini.safety_settings', []);
        $this->generationConfig = config('ai.gemini.generation_config', []);
        $this->maxRetries = (int) config('ai.gemini.max_retries', 3);
    }

    /**
     *  Check if daily quota is available
     */
    protected function canMakeRequest(): bool
    {
        $key = 'gemini_requests_' . date('Y-m-d');
        try {
            $count = Cache::get($key, 0);
        } catch (\Throwable $e) {
            Log::warning('Gemini quota cache is unavailable; allowing request', [
                'error' => $e->getMessage(),
            ]);
            return true;
        }

        // Free tier: 1,500 requests per day
        $dailyLimit = config('ai.limits.max_requests_per_day', 1500);

        return $count < $dailyLimit;
    }

    /**
     * Track request count
     */
    protected function trackRequest(): void
    {
        $key = 'gemini_requests_' . date('Y-m-d');
        try {
            Cache::increment($key);
            Cache::put($key, Cache::get($key), now()->endOfDay());
        } catch (\Throwable $e) {
            Log::warning('Gemini quota could not be tracked', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate content using Gemini
     */
    // public function generateContent(string $prompt, array $options = []): array
    // {
    //     try {
    //         $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

    //         $payload = [
    //             'contents' => [
    //                 [
    //                     'parts' => [
    //                         ['text' => $prompt],
    //                     ],
    //                 ],
    //             ],
    //             'safetySettings' => $this->safetySettings,
    //             'generationConfig' => array_merge(
    //                 $this->generationConfig,
    //                 $options
    //             ),
    //         ];

    //         $response = Http::withHeaders([
    //             'Content-Type' => 'application/json',
    //         ])->post($url, $payload);

    //         if (!$response->successful()) {
    //             Log::error('Gemini API error:', [
    //                 'status' => $response->status(),
    //                 'body' => $response->body(),
    //             ]);

    //             return [
    //                 'success' => false,
    //                 'error' => 'Gemini API error: ' . $response->body(),
    //             ];
    //         }

    //         $data = $response->json();

    //         $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    //         $finishReason = $data['candidates'][0]['finishReason'] ?? 'STOP';

    //         // Get token usage
    //         $usageMetadata = $data['usageMetadata'] ?? [];
    //         $promptTokens = $usageMetadata['promptTokenCount'] ?? 0;
    //         $candidatesTokens = $usageMetadata['candidatesTokenCount'] ?? 0;
    //         $totalTokens = $usageMetadata['totalTokenCount'] ?? 0;

    //         return [
    //             'success' => true,
    //             'content' => $text,
    //             'finish_reason' => $finishReason,
    //             'usage' => [
    //                 'prompt_tokens' => $promptTokens,
    //                 'completion_tokens' => $candidatesTokens,
    //                 'total_tokens' => $totalTokens,
    //             ],
    //         ];

    //     } catch (\Exception $e) {
    //         Log::error('Gemini generation failed:', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);

    //         return [
    //             'success' => false,
    //             'error' => $e->getMessage(),
    //         ];
    //     }
    // }


    public function generateContent(string $prompt, array $options = []): array
    {
        if (!$this->canMakeRequest()) {
            return [
                'success' => false,
                'error' => 'Daily API quota exceeded. Please try again tomorrow.',
            ];
        }

        $attempts = 0;

        while ($attempts < $this->maxRetries) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'safetySettings' => $this->safetySettings,
                    'generationConfig' => array_merge(
                        $this->generationConfig,
                        $options
                    ),
                ];

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($url, $payload);

                // Handle rate limit (429)
                if ($response->status() === 429) {
                    $attempts++;
                    $retryAfter = $response->header('Retry-After') ?? (2 ** $attempts);

                    Log::warning('Gemini rate limit hit', [
                        'attempt' => $attempts,
                        'retry_after' => $retryAfter,
                    ]);

                    if ($attempts < $this->maxRetries) {
                        sleep((int) $retryAfter);
                        continue;
                    }

                    return [
                        'success' => false,
                        'error' => 'Rate limit exceeded. Please try again later.',
                    ];
                }

                if (!$response->successful()) {
                    Log::error('Gemini API error:', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Gemini API error: ' . $response->body(),
                    ];
                }

                $data = $response->json();

                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $finishReason = $data['candidates'][0]['finishReason'] ?? 'STOP';

                $usageMetadata = $data['usageMetadata'] ?? [];
                $this->trackRequest();

                return [
                    'success' => true,
                    'content' => $text,
                    'finish_reason' => $finishReason,
                    'usage' => [
                        'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
                        'completion_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
                        'total_tokens' => $usageMetadata['totalTokenCount'] ?? 0,
                    ],
                ];

            } catch (\Exception $e) {
                $attempts++;

                Log::error('Gemini generation failed:', [
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                ]);

                if ($attempts >= $this->maxRetries) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }

                sleep(2 ** $attempts);
            }
        }

        // Track successful request
        $this->trackRequest();

        return [
            'success' => false,
            'error' => 'Max retries exceeded',
        ];
    }

    /**
     * Stream content using Gemini
     */
    public function streamContent(string $prompt, callable $callback, array $options = []): void
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:streamGenerateContent?key={$this->apiKey}&alt=sse";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'safetySettings' => $this->safetySettings,
                'generationConfig' => array_merge(
                    $this->generationConfig,
                    $options
                ),
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->withOptions([
                        'stream' => true,
                    ])->post($url, $payload);

            if (!$response->successful()) {
                Log::error('Gemini streaming error:', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return;
            }

            $body = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $chunk = $body->read(1024);
                $buffer .= $chunk;

                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    if (str_starts_with($line, 'data: ')) {
                        $jsonData = substr($line, 6);

                        try {
                            $data = json_decode($jsonData, true);

                            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                            if ($text) {
                                $callback($text);
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to parse streaming chunk:', [
                                'line' => $line,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Gemini streaming failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Generate embeddings using Gemini
     */
    public function generateEmbedding(string $text): array
    {
        try {
            $model = 'text-embedding-004';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:embedContent?key={$this->apiKey}";

            $payload = [
                'model' => "models/{$model}",
                'content' => [
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if (!$response->successful()) {
                Log::error('Gemini embedding error:', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->getZeroEmbedding();
            }

            $data = $response->json();
            $embedding = $data['embedding']['values'] ?? [];

            return $embedding;

        } catch (\Exception $e) {
            Log::error('Gemini embedding failed:', [
                'error' => $e->getMessage(),
            ]);

            return $this->getZeroEmbedding();
        }
    }

    /**
     * Get zero embedding (fallback)
     */
    protected function getZeroEmbedding(): array
    {
        return array_fill(0, 768, 0);
    }

    /**
     * Check if Gemini is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
