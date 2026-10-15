<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications — current user's notifications.
     * Matches the PWA AppNotification[] contract.
     */
    public function index(Request $request)
    {
        $items = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'items' => $items->map(fn ($n) => [
                'id' => (string) $n->id,
                'judul' => $n->title ?? '',
                'isi' => $n->body ?? '',
                'dibuat_at' => $n->created_at->toIso8601String(),
                'sudah_dibaca' => $n->read_at !== null,
            ])->values(),
        ]);
    }

    /**
     * POST /api/v1/notifications/{id}/read — mark as read.
     */
    public function read(Request $request, Notification $notification)
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }
}
