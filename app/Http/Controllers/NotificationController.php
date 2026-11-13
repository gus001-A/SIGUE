<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{

    /** Últimas notificaciones para la campana del encabezado. */
    public function feed(Request $request)
    {
        $q = Notification::where('user_id', $request->user()->id);

        return response()->json([
            'unread' => (clone $q)->whereNull('read_at')->count(),
            'items' => $q->latest()->limit(8)->get()->map->toView()->values(),
        ]);
    }

    public function read(Request $request, int $id)
    {
        $n = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $n->update(['read_at' => $n->read_at ?? now()]);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    public function readAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Notificaciones marcadas como leídas.');
    }

    public function destroy(Request $request, int $id)
    {
        Notification::where('user_id', $request->user()->id)->whereKey($id)->delete();

        return back();
    }
}
