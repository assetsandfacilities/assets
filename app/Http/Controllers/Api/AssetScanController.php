<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetScanLog;
use App\Models\Item;
use App\Models\Room;
use Illuminate\Http\Request;

class AssetScanController extends Controller
{
    /**
     * Recent scan history for the logged-in user (Monitoring module).
     * ?filter=mismatch to only show unresolved mismatches.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->canHandleAssetScans(), 403);

        $query = AssetScanLog::with(['item', 'user', 'resolver'])
            ->where('user_id', $request->user()->id);

        if ($request->get('filter') === 'mismatch') {
            $query->where('status', 'mismatch')->whereNull('resolved_at');
        }

        return $query->latest()->limit(50)->get();
    }

    /**
     * Submit a scan. The housekeeper selects their Floor + Room on the mobile app
     * BEFORE scanning the QR code, so this endpoint receives the item's QR/code plus
     * the confirmed floor_id/room_id. The match/mismatch decision compares that
     * confirmed room against the item's assigned room (by ID, not free-text name).
     * GPS is no longer collected or used for verification.
     */
    public function store(Request $request)
    {
        abort_unless($request->user()->canHandleAssetScans(), 403);

        $data = $request->validate([
            'code' => ['required', 'string'],
            'floor_id' => ['required', 'exists:floors,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $normalizedCode = trim(urldecode($data['code']));
        $item = Item::where('item_type', 'CAPEX')
            ->where(function ($q) use ($normalizedCode) {
                $q->where('item_code', $normalizedCode)->orWhere('qr_value', $normalizedCode);
            })
            ->firstOrFail();

        $scannedRoom = Room::with('floor')->findOrFail($data['room_id']);

        // Prefer the real FK relationship (item.room_id) for the match decision. Fall
        // back to comparing the legacy free-text room_assigned column only for items
        // that haven't been migrated to the Floor/Room tables yet.
        if ($item->room_id !== null) {
            $roomsMatch = (int) $item->room_id === (int) $data['room_id'];
        } else {
            $roomsMatch = $this->normalizeRoom((string) $item->room_assigned) !== ''
                && $this->normalizeRoom((string) $item->room_assigned) === $this->normalizeRoom($scannedRoom->name);
        }

        $status = $roomsMatch ? 'matched' : 'mismatch';
        $expectedLabel = $item->room?->floor
            ? "{$item->room->label()} ({$item->room->floor->name})"
            : ($item->room_assigned ?: 'Unassigned');
        $scannedLabel = $scannedRoom->floor
            ? "{$scannedRoom->label()} ({$scannedRoom->floor->name})"
            : $scannedRoom->label();

        $log = AssetScanLog::create([
            'item_id' => $item->id,
            'user_id' => $request->user()->id,
            'scanned_floor_id' => $data['floor_id'],
            'scanned_room_id' => $data['room_id'],
            'expected_room' => $expectedLabel,
            'scanned_room' => $scannedLabel,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ]);

        // If this item had an open mismatch and it's now confirmed back in its assigned room,
        // auto-resolve the earlier mismatch entry too.
        if ($status === 'matched') {
            AssetScanLog::where('item_id', $item->id)
                ->where('status', 'mismatch')
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id]);
        }

        return response()->json([
            'status' => $status,
            'expected_room' => $expectedLabel,
            'scanned_room' => $scannedLabel,
            'item' => $item->load('category'),
            'scan_log_id' => $log->id,
        ], 201);
    }

    /**
     * Manually mark a mismatch as resolved once the item has been physically relocated,
     * for cases where re-scanning at the assigned room isn't practical right away.
     */
    public function resolve(Request $request, AssetScanLog $assetScanLog)
    {
        abort_unless($request->user()->canHandleAssetScans(), 403);
        abort_unless($assetScanLog->isUnresolvedMismatch(), 422, 'This scan is not an open mismatch.');

        $assetScanLog->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id]);

        return response()->json(['message' => 'Mismatch marked as resolved.', 'scan_log' => $assetScanLog->fresh(['item', 'resolver'])]);
    }

    private function normalizeRoom(string $value): string
    {
        $trimmed = trim($value);
        $digits = preg_replace('/\D/', '', $trimmed);
        if ($digits !== '') {
            return $digits;
        }
        return strtolower(str_replace(['room', 'rm', '-', ' '], '', $trimmed));
    }
}
