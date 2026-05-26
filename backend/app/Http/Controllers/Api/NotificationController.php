<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->getKey())
            ->when($request->boolean('unread_only'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return NotificationResource::collection($notifications);
    }

    public function update(UpdateNotificationRequest $request, Notification $notification)
    {
        abort_unless(
            $notification->user_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $notification->update([
            'read_at' => $request->boolean('read') ? now() : null,
        ]);

        return response()->json([
            'message' => __('api.notifications.updated'),
            'data' => new NotificationResource($notification->fresh()),
        ]);
    }

    public function markAllRead(Request $request)
    {
        Notification::query()
            ->where('user_id', $request->user()->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => __('api.notifications.all_read'),
        ]);
    }

    public function destroy(Request $request, Notification $notification)
    {
        abort_unless(
            $notification->user_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $notification->delete();

        return response()->noContent();
    }
}
