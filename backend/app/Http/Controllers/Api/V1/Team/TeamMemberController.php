<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateMemberRequest;
use App\Http\Resources\Team\TeamMemberCollection;
use App\Http\Resources\TeamMemberResource;
use App\Services\Team\TeamMemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeamMemberController extends Controller
{

    protected TeamMemberService $service;

    public function __construct(TeamMemberService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of team members
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only([
                'search',
                'department',
                'availability_status',
                'role',
                'sort',
                'direction',
                'per_page',
            ]);

            $members = $this->service->getMembers($filters);

            return new TeamMemberCollection($members);

        } catch (\Exception $e) {
            Log::error('Failed to get team members:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team members.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single team member
     */
    public function show($id)
    {
        try {
            $member = $this->service->getMember($id);

            return new TeamMemberResource($member);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get team member:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team member.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a team member
     */
    public function update(UpdateMemberRequest $request, $id)
    {
        try {
            $member = $this->service->updateMember($id, $request->validated());

            return (new TeamMemberResource($member))
                ->additional([
                    'message' => 'Team member updated successfully.',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update team member:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update team member.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a team member
     */
    public function destroy($id)
    {
        try {
            $this->service->removeMember($id);

            return response()->json([
                'success' => true,
                'message' => 'Team member removed successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove member.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to remove team member:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove team member.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get team statistics
     */
    public function statistics(Request $request)
    {
        try {
            $statistics = $this->service->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get team statistics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team statistics.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get departments for filtering
     */
    public function departments(Request $request)
    {
        try {
            $departments = $this->service->getDepartments();

            return response()->json([
                'success' => true,
                'data' => $departments,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get departments:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
