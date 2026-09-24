<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Http\Resources\Admin\UserDetailResource;
use App\Models\User;
use App\Services\User\UserLifecycleService;
use App\Traits\GuardsSystemUsers;

class UserLifecycleController extends Controller
{
    use GuardsSystemUsers;

    public function __construct(
        protected UserLifecycleService $lifecycle,
    ) {
    }

    public function activate(User $user)
    {
        $guard = $this->protectSystemUser($user);
        if ($guard) {
            return $guard;
        }

        $user = $this->lifecycle->activate($user);

        return (new UserDetailResource($user))
            ->additional(['message' => 'User activated successfully.']);
    }

    public function suspend(SuspendUserRequest $request, User $user)
    {
        $guard = $this->protectSystemUser($user);
        if ($guard) {
            return $guard;
        }

        $user = $this->lifecycle->suspend(
            $request->user(),
            $user,
            $request->input('reason'),
        );

        return (new UserDetailResource($user))
            ->additional(['message' => 'User suspended successfully.']);
    }
}