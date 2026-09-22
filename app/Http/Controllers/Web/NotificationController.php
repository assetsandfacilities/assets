<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = Auth::user()->notifications()->latest()->paginate(12);
        return view('notifications.index', compact('notifications'));
    }

    public function markAsRead(string $notification): RedirectResponse
    {
        $record = Auth::user()->notifications()->whereKey($notification)->firstOrFail();
        if (!$record->read_at) {
            $record->markAsRead();
        }
        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(): RedirectResponse
    {
        Auth::user()->unreadNotifications->markAsRead();
        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Deletes a single notification for the currently logged-in user only --
     * notifications are per-account, so this never touches other users' copies
     * of the same event.
     */
    public function destroy(string $notification): RedirectResponse
    {
        $record = Auth::user()->notifications()->whereKey($notification)->firstOrFail();
        $record->delete();
        return redirect()->back()->with('success', 'Notification removed.');
    }

    /**
     * Clears every notification stored for the currently logged-in user.
     * Scoped to Auth::user() so it can never wipe another account's notifications.
     */
    public function clearAll(): RedirectResponse
    {
        Auth::user()->notifications()->delete();
        return redirect()->back()->with('success', 'All notifications cleared.');
    }
}
