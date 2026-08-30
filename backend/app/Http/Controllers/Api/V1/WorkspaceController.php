<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WorkspaceController extends Controller
{
    protected WorkspaceService $workspaceService;

    public function __construct(WorkspaceService $workspaceService)
    {
        $this->workspaceService = $workspaceService;
    }

    /**
     * Get the current workspace details.
     */
    public function show(Request $request)
    {
        try {
            $workspace = $this->workspaceService->getWorkspace();

            if (!$workspace) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workspace not found',
                ], 404);
            }

            return (new WorkspaceResource($workspace))
                ->additional([
                    'success' => true,
                    'message' => 'Workspace retrieved successfully',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to get workspace:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workspace: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the current workspace.
     */
    public function update(UpdateWorkspaceRequest $request)
    {
        try {
            $workspace = $this->workspaceService->updateWorkspace($request->validated());

            return (new WorkspaceResource($workspace))
                ->additional([
                    'success' => true,
                    'message' => 'Workspace updated successfully',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to update workspace:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update workspace: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update workspace logo.
     */
    public function updateLogo(Request $request)
    {
        try {
            $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $workspace = $this->workspaceService->updateLogo($request->file('logo'));

            return (new WorkspaceResource($workspace))
                ->additional([
                    'success' => true,
                    'message' => 'Logo updated successfully',
                ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update logo:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update logo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete workspace logo.
     */
    public function deleteLogo(Request $request)
    {
        try {
            $workspace = $this->workspaceService->deleteLogo();

            return (new WorkspaceResource($workspace))
                ->additional([
                    'success' => true,
                    'message' => 'Logo deleted successfully',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete logo:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete logo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update workspace favicon.
     */
    public function updateFavicon(Request $request)
    {
        try {
            $request->validate([
                'favicon' => 'required|image|mimes:jpeg,png,jpg,gif,ico|max:1024',
            ]);

            $workspace = $this->workspaceService->updateFavicon($request->file('favicon'));

            return (new WorkspaceResource($workspace))
                ->additional([
                    'success' => true,
                    'message' => 'Favicon updated successfully',
                ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update favicon:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update favicon: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete workspace favicon.
     */
    public function deleteFavicon(Request $request)
    {
        try {
            $workspace = $this->workspaceService->deleteFavicon();

            return (new WorkspaceResource($workspace))
                ->additional([
                    'success' => true,
                    'message' => 'Favicon deleted successfully',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete favicon:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete favicon: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update business hours.
     */
    public function updateBusinessHours(Request $request)
    {
        try {
            $request->validate([
                'business_hours' => 'required|array',
                'business_hours.*.enabled' => 'boolean',
                'business_hours.*.open' => 'nullable|string|date_format:H:i',
                'business_hours.*.close' => 'nullable|string|date_format:H:i',
            ]);

            $workspace = $this->workspaceService->updateBusinessHours($request->business_hours);

            return (new WorkspaceResource($workspace))
                ->additional([
                    'success' => true,
                    'message' => 'Business hours updated successfully',
                ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update business hours:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update business hours: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get workspace statistics.
     */
    public function statistics(Request $request)
    {
        try {
            $workspace = $this->workspaceService->getWorkspace();

            if (!$workspace) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workspace not found',
                ], 404);
            }

            $statistics = $this->workspaceService->getStatistics($workspace);

            return response()->json([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get statistics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

}
