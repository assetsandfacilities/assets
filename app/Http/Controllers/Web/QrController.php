<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class QrController extends Controller
{
    /**
     * Bulk-added CAPEX units share a base name ("Projector" ->
     * "Projector (Unit 1 of 10)", "Projector (Unit 2 of 10)", ...). Stripping
     * that suffix lets us find every sibling unit from a single one that was
     * added together, so they can be offered as a batch for printing --
     * each keeping its own distinct item_code/qr_value.
     */
    public static function baseName(string $name): string
    {
        return trim(preg_replace('/\s*\(Unit\s+\d+\s+of\s+\d+\)\s*$/i', '', $name));
    }

    private function batchSiblings(Item $item)
    {
        $base = self::baseName($item->name);

        return Item::where('item_type', 'CAPEX')
            ->where(function ($q) use ($base) {
                $escaped = addcslashes($base, '%_');
                $q->where('name', $base)
                  ->orWhere('name', 'like', $escaped . ' (Unit %');
            })
            ->orderBy('item_code')
            ->get();
    }

    public function index(Request $request)
    {
        $capexItems = Item::where('item_type', 'CAPEX')->orderBy('name')->get();
        $selectedItem = null;
        $normalizedCode = null;
        $verifiedRoom = null;
        $verificationStatus = null;
        $verificationMessage = null;

        if ($request->filled('code')) {
            $normalizedCode = trim((string) $request->code);

            if (preg_match('#/items/(\d+)$#', $normalizedCode, $matches)) {
                $selectedItem = Item::with('category')->find($matches[1]);
            }

            if (!$selectedItem) {
                $selectedItem = Item::with('category')
                    ->where('item_code', $normalizedCode)
                    ->orWhere('qr_value', $normalizedCode)
                    ->first();
            }
        }

        if ($selectedItem && $request->filled('verify_room')) {
            $verifiedRoom = trim((string) $request->verify_room);
            $assignedRoom = trim((string) ($selectedItem->room_assigned ?? ''));

            if ($assignedRoom === '') {
                $verificationStatus = 'no-room';
                $verificationMessage = 'This asset has no assigned room yet. Update the asset record first to use location verification.';
            } elseif (strcasecmp($verifiedRoom, $assignedRoom) === 0) {
                $verificationStatus = 'matched';
                $verificationMessage = 'Asset verified successfully. The scanned asset matches its assigned room.';
            } else {
                $verificationStatus = 'mismatch';
                $verificationMessage = 'Location mismatch detected. The scanned asset does not match its assigned room.';
            }
        }

        return view('qr.index', compact(
            'capexItems',
            'selectedItem',
            'normalizedCode',
            'verifiedRoom',
            'verificationStatus',
            'verificationMessage'
        ));
    }

    public function show(Item $item)
    {
        abort_if(!$item->qr_value, 404, 'QR only available for CAPEX item.');

        $qrPayload = $item->qr_value ?: $item->item_code;
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($qrPayload);

        return view('qr.show', compact('item', 'qrUrl', 'qrPayload'));
    }

    /**
     * Print-selection screen: shown when the admin clicks the QR/print icon
     * on a CAPEX item. Lists every sibling unit from the same bulk-add batch
     * (or just the one item, if it wasn't added in bulk) so the admin can
     * choose to print all of them or hand-pick specific units.
     */
    public function batch(Item $item)
    {
        abort_if($item->item_type !== 'CAPEX' || !$item->qr_value, 404, 'QR only available for CAPEX item.');

        $siblings = $this->batchSiblings($item);
        $baseName = self::baseName($item->name);

        return view('qr.batch', compact('siblings', 'baseName', 'item'));
    }

    /**
     * Renders one printable QR label per selected unit. Each label uses that
     * unit's own item_code/qr_value, so units within the same batch still
     * carry distinct QR payloads.
     */
    public function printBatch(Request $request)
    {
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        abort_if($ids->isEmpty(), 404, 'No units selected for printing.');

        $items = Item::whereIn('id', $ids)
            ->where('item_type', 'CAPEX')
            ->whereNotNull('qr_value')
            ->orderBy('item_code')
            ->get()
            ->map(function ($item) {
                $payload = $item->qr_value ?: $item->item_code;
                $item->qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($payload);
                $item->qrPayload = $payload;
                return $item;
            });

        abort_if($items->isEmpty(), 404, 'No printable units found for the selected units.');

        return view('qr.print', compact('items'));
    }
}
