<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\MarkNotificationsReadRequest;
use App\Http\Resources\Admin\PlatformNotificationResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Platform notification center API. Every query is scoped to the authenticated
 * Super Admin's own notification relation; ids are never trusted from clients.
 */
class PlatformNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = $user->notifications()->latest();
        $this->applyFilters($query, $request);

        $perPage = $this->perPage($request);
        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data'    => PlatformNotificationResource::collection($paginator->items())->resolve(),
            'meta'    => [
                'unread_count' => $this->currentUnreadCount(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['unread_count' => $this->currentUnreadCount()],
        ]);
    }

    public function markAsRead(string $notification): JsonResponse
    {
        $model = $this->findOwned($notification);

        if ($model === null) {
            return $this->notFound();
        }

        if ($model->read_at === null) {
            $model->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => (string) $model->id,
                'read_at'      => optional($model->fresh()->read_at)->toIso8601String(),
                'unread_count' => $this->currentUnreadCount(),
            ],
        ]);
    }

    public function markAsUnread(string $notification): JsonResponse
    {
        $model = $this->findOwned($notification);

        if ($model === null) {
            return $this->notFound();
        }

        $model->forceFill(['read_at' => null])->save();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => (string) $model->id,
                'read_at'      => null,
                'unread_count' => $this->currentUnreadCount(),
            ],
        ]);
    }

    public function markAllAsRead(MarkNotificationsReadRequest $request): JsonResponse
    {
        $query = Auth::user()->unreadNotifications();
        $this->applyFilters($query, $request);

        $query->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data'    => [
                'unread_count' => 0,
            ],
        ]);
    }

    public function destroy(string $notification): JsonResponse
    {
        $deleted = Auth::user()->notifications()->whereKey($notification)->delete();

        if ($deleted === 0) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $notification,
                'unread_count' => $this->currentUnreadCount(),
            ],
        ]);
    }

    protected function currentUnreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    protected function findOwned(string $notification)
    {
        return Auth::user()->notifications()->whereKey($notification)->first();
    }

    protected function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Notification not found.',
        ], 404);
    }

    protected function perPage(Request $request): int
    {
        $default = (int) config('notifications.platform.per_page', 20);
        $max     = (int) config('notifications.platform.max_per_page', 100);

        return min(max((int) $request->query('per_page', $default), 1), $max);
    }

    /**
     * @param  MorphMany<*, *>|Builder<*>  $query
     */
    protected function applyFilters($query, Request $request): void
    {
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        } elseif ($request->boolean('read')) {
            $query->whereNotNull('read_at');
        }

        if ($request->filled('category')) {
            $this->whereData($query, 'category', (string) $request->query('category'));
        }

        if ($request->filled('severity')) {
            $this->whereData($query, 'severity', (string) $request->query('severity'));
        }
    }

    /**
     * Filter on a key inside the JSON "data" column. The column is stored as
     * text, so each driver needs its own JSON extraction expression.
     *
     * @param  MorphMany<*, *>|Builder<*>  $query
     */
    protected function whereData($query, string $key, string $value): void
    {
        $driver = $query->getConnection()->getDriverName();

        match ($driver) {
            'pgsql' => $query->whereRaw('(data::jsonb ->> ?) = ?', [$key, $value]),
            'sqlite' => $query->whereRaw('json_extract(data, ?) = ?', ['$."'.$key.'"', $value]),
            'mysql', 'mariadb' => $query->whereRaw('json_unquote(json_extract(data, ?)) = ?', ['$."'.$key.'"', $value]),
            default => $query->where('data', 'like', '%"'.$key.'":"'.$value.'"%'),
        };
    }
}
