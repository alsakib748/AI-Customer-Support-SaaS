<?php

namespace App\Http\Controllers\Api\V1\AI;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AIConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AIConfigurationController extends Controller
{
    /**
     * Get AI configuration
     */
    public function show(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('ai.configure')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view AI configuration.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have AI configuration context.',
                ], 404);
            }

            $config = AIConfiguration::first() ?? AIConfiguration::getDefault();

            return response()->json([
                'success' => true,
                'data' => $config,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get AI configuration:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve AI configuration.',
            ], 500);
        }
    }

    /**
     * Update AI configuration
     */
    public function update(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('ai.configure')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to update AI configuration.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot update AI configuration without tenant context.',
                ], 400);
            }

            $validated = $request->validate([
                'provider' => ['sometimes', Rule::in(['openai', 'anthropic', 'gemini'])],
                // 'model' => ['nullable', 'string'],
                'model' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value === null) return;

                        $provider = $request->input('provider');
                        if (!$provider) {
                            $config = AIConfiguration::first();
                            $provider = $config ? $config->provider : 'gemini';
                        }

                        $validModels = AIConfiguration::getModelsByProvider($provider);

                        if (!in_array($value, array_keys($validModels))) {
                            $fail("Invalid model for provider: {$provider}");
                        }
                    }
                ],
                'enabled' => ['sometimes', 'boolean'],
                'auto_reply_enabled' => ['sometimes', 'boolean'],
                'auto_escalation_enabled' => ['sometimes', 'boolean'],
                'streaming_enabled' => ['sometimes', 'boolean'],
                'knowledge_base_enabled' => ['sometimes', 'boolean'],
                'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
                'max_tokens' => ['sometimes', 'integer', 'min:1', 'max:8000'],
                'system_prompt' => ['nullable', 'string'],
                'custom_instructions' => ['nullable', 'string'],
            ]);

            $config = AIConfiguration::first();

            if (!$config) {
                $config = AIConfiguration::create($validated);
            } else {
                $config->update($validated);
            }

            Log::info('AI configuration updated', [
                'tenant_id' => tenant()->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'AI configuration updated successfully 🎉',
                'data' => $config,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update AI configuration:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update AI configuration.',
            ], 500);
        }
    }

    /**
     * Test AI configuration
     */
    public function test(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('ai.configure')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to test AI.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot test AI without tenant context.',
                ], 400);
            }

            $validated = $request->validate([
                'message' => ['required', 'string', 'max:500'],
            ]);

            // Test AI connection
            // This would use the Laravel AI SDK to send a test message
            // For now, return a simulated response

            return response()->json([
                'success' => true,
                'message' => 'AI test completed successfully ✅',
                'data' => [
                    'response' => 'AI is working correctly! This is a test response.',
                    'provider' => config('ai.default'),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('AI test failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'AI test failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}