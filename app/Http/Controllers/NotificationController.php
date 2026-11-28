<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Les notifications de l'utilisateur connecte : liste, lecture, suppression. Jamais celles d'un autre compte. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', [
            'notifications' => $request->user()->siteNotifications()->paginate(20),
        ]);
    }

    public function read(Request $request, int $notification): RedirectResponse
    {
        $request->user()->siteNotifications()->unread()->findOrFail($notification)->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->siteNotifications()->unread()->update(['read_at' => now()]);

        return back();
    }

    public function destroy(Request $request, int $notification): RedirectResponse
    {
        $request->user()->siteNotifications()->findOrFail($notification)->delete();

        return back();
    }
}
