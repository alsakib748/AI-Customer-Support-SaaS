<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevokeSessionsRequest;
use App\Http\Resources\Admin\UserDetailResource;
use App\Models\User;
use App\Services\User\UserSessionService;
use App\Traits\GuardsSystemUsers;

class UserSessionController extends Controller
{
    use GuardsSystemUsers;

    public function __construct(
        protected UserSessionService $sessions,
    ) {
    }

    public function revokeAll(RevokeSessionsRequest $request, User $user)
    {
        $guard = $this->protectSystemUser($user);
        if ($guard) {
            return $guard;
        }

        $user = $this->sessions->revokeAll(
            $request->user(),
            $user,
            $request->input('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'All sessions revoked. The user must sign in again.',
            'data'    => new UserDetailResource($user),
        ]);
    }
}