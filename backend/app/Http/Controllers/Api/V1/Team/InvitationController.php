<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\InviteMemberRequest;
use App\Services\Team\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    protected InvitationService $service;

    public function __construct(InvitationService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all invitations
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['search', 'status', 'per_page']);
            $invitations = $this->service->getInvitations($filters);

            return response()->json([
                'success' => true,
                'data' => $invitations->items(),
                'meta' => [
                    'current_page' => $invitations->currentPage(),
                    'per_page' => $invitations->perPage(),
                    'total' => $invitations->total(),
                    'last_page' => $invitations->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invitations:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invitations.',
            ], 500);
        }
    }

    /**
     * Create a new invitation
     */
    public function store(InviteMemberRequest $request)
    {
        try {
            $invitation = $this->service->createInvitation($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully.',
                'data' => $invitation,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to create invitation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accept an invitation
     */
    public function accept(Request $request, $token)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'first_name' => 'nullable|string|max:100',
                'last_name' => 'nullable|string|max:100',
                'password' => 'nullable|string|min:8',
            ]);

            $member = $this->service->acceptInvitation($token, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Invitation accepted successfully! Welcome to the team 🎉',
                'data' => $member,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to accept invitation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to accept invitation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resend an invitation
     */
    public function resend($id)
    {
        try {
            $invitation = $this->service->resendInvitation($id);

            return response()->json([
                'success' => true,
                'message' => 'Invitation resent successfully.',
                'data' => $invitation,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot resend invitation.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to resend invitation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend invitation.',
            ], 500);
        }
    }

    /**
     * Revoke an invitation
     */
    public function destroy($id)
    {
        try {
            $this->service->revokeInvitation($id);

            return response()->json([
                'success' => true,
                'message' => 'Invitation revoked successfully.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revoke invitation.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to revoke invitation:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke invitation.',
            ], 500);
        }
    }
}