<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\Admin\UserDetailResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\User\UserListService;
use App\Services\User\UserMembershipService;
use App\Services\User\UserService;
use App\Traits\GuardsSystemUsers;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use GuardsSystemUsers;

    public function __construct(
        protected UserListService $list,
        protected UserMembershipService $membership,
        protected UserService $service,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'search',
            'status',
            'scope',
            'verification',
            'tenant_id',
            'created_from',
            'created_to',
            'sort',
            'direction',
            'per_page',
        ]);

        $users = $this->list->paginate($filters);

        return UserResource::collection($users);
    }

    public function stats(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->list->statistics(),
        ]);
    }

    public function show(User $user)
    {
        return new UserDetailResource($user);
    }

    public function store(CreateUserRequest $request)
    {
        $user = $this->service->create($request->user(), $request->validated());

        return (new UserDetailResource($user))
            ->additional(['message' => 'User created. Default password is 11111111 — the user must change it at first login.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $guard = $this->protectSystemUser($user);
        if ($guard) {
            return $guard;
        }

        $user = $this->service->update($request->user(), $user, $request->validated());

        return (new UserDetailResource($user))
            ->additional(['message' => 'User updated successfully.']);
    }

    public function memberships(User $user)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->membership->memberships($user),
        ]);
    }

    public function activity(Request $request, User $user)
    {
        $limit = min((int) $request->input('limit', 50), 100);

        $logs = AuditLog::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere(function ($resource) use ($user) {
                    $resource->where('resource_type', 'user')
                        ->where('resource_id', $user->id);
                });
        })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $logs->map(fn (AuditLog $log) => [
                'id'           => $log->id,
                'action'       => $log->action,
                'action_label' => $log->action_label,
                'actor_id'     => $log->user_id,
                'actor_name'   => $log->user_name,
                'resource_type'=> $log->resource_type,
                'resource_id'  => $log->resource_id,
                'metadata'     => $log->metadata,
                'ip_address'   => $log->ip_address,
                'created_at'   => $log->created_at?->toISOString(),
            ])->values(),
        ]);
    }
}