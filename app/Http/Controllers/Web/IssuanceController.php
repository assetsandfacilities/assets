<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Issuance;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\IssuanceStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IssuanceController extends Controller
{
    /**
     * Notifications are a side effect, not the actual issuance record -- if mail
     * fails (e.g. Brevo hiccup) the issuance must still be considered recorded
     * successfully, so every notify() call is isolated in its own try/catch.
     */
    private function safeNotify($notifiable, $notification): void
    {
        if (!$notifiable) {
            return;
        }
        try {
            $notifiable->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function index()
    {
        $issuances = Issuance::with(['requisition','issuer','receiver'])->latest()->paginate(10);
        return view('issuances.index', compact('issuances'));
    }

    public function create()
    {
        return view('issuances.create', [
            'requisitions' => Requisition::where('status', 'approved')
                ->with(['items.item', 'user'])
                ->orderByDesc('id')
                ->get(),
            // "Received By" tracks who on the Asset Management side physically
            // received/logged the item hand-off -- not the requestor -- so the
            // list is limited to Asset Management Admins and Super Admins
            // instead of every account in the system.
            'users' => User::whereIn('role', ['admin', 'super_admin'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'requisition_id' => ['required','exists:requisitions,id'],
            'received_by' => ['required', Rule::exists('users', 'id')->whereIn('role', ['admin', 'super_admin'])],
            'remarks' => ['nullable','string'],
        ]);

        $requisition = Requisition::findOrFail($data['requisition_id']);
        if ($requisition->status !== 'approved') {
            return back()->withErrors(['requisition_id' => 'Only approved requisitions can be issued.'])->withInput();
        }

        $issuance = Issuance::create([
            'requisition_id' => $requisition->id,
            'issued_by' => Auth::id(),
            'received_by' => $data['received_by'],
            'issued_at' => now(),
            'status' => 'issued',
            'remarks' => $data['remarks'] ?? null,
        ]);

        $requisition->update(['status' => 'issued']);

        // Notify the original requestor (in-system + registered email) that their
        // charge slip has been issued.
        $this->safeNotify(
            $requisition->user,
            new IssuanceStatusNotification(
                $issuance->fresh('requisition'),
                'Your Requisition Has Been Issued',
                'Good news! The items for your requisition "' . $requisition->requisition_no . '" have been issued.'
            )
        );

        return redirect()->route('issuances.index')->with('success', 'Issuance recorded successfully.');
    }

    public function returnItem(Issuance $issuance)
    {
        $issuance->update(['status' => 'returned']);

        $this->safeNotify(
            $issuance->requisition?->user,
            new IssuanceStatusNotification(
                $issuance->fresh('requisition'),
                'Issued Item Marked as Returned',
                'The item(s) issued under requisition "' . ($issuance->requisition->requisition_no ?? 'N/A') . '" have been marked as returned.'
            )
        );

        return redirect()->route('issuances.index')->with('success', 'Item marked as returned.');
    }

    /**
     * Only Super Admins may delete an issuance record entirely -- locked to the
     * highest privilege role since this is user-submitted data.
     */
    public function destroy(Issuance $issuance)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $issuance->delete();
        return redirect()->route('issuances.index')->with('success', 'Issuance record deleted successfully.');
    }
}
