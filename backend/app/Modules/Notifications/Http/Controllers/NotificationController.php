<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * My notifications (D34). Every query goes through `$user->notifications()`, so another
 * learner's notification answers 404.
 */
final class NotificationController extends Controller
{
    /**
     * List my notifications, newest first.
     *
     * `filter[unread]=1` keeps unread ones only; `per_page` 1 to 50 (default 20).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $query = $request->user()->notifications()->latest()->orderByDesc('id');
        if ($request->boolean('filter.unread')) {
            $query->whereNull('read_at');
        }
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (DatabaseNotification $n) => self::present($n))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    /** How many unread notifications I have; the header polls this. */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['count' => $request->user()->unreadNotifications()->count()]]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $notification = $this->mine($request, $id);
        $notification->markAsRead();

        return response()->json(['data' => self::present($notification)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['count' => 0]]);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->mine($request, $id)->delete();

        return response()->noContent();
    }

    private function mine(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private static function present(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'params' => $n->data['params'] ?? [],
            'url' => $n->data['url'] ?? null,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
