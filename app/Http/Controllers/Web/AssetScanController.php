<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssetScanLog;
use App\Models\Floor;
use App\Models\Room;
use Illuminate\Http\Request;

class AssetScanController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->canHandleAssetScans(), 403);

        [$logs, $meta] = $this->filteredLogs($request, paginate: true);

        return view('asset_scans.index', array_merge($meta, [
            'logs' => $logs,
            'canDelete' => auth()->user()->isSuperAdmin(),
        ]));
    }

    /**
     * Printable report of the currently filtered scan log (no pagination, clean layout).
     * Opened in a new tab from the "Print" button on the monitoring page.
     */
    public function print(Request $request)
    {
        abort_unless(auth()->user()->canHandleAssetScans(), 403);

        [$logs, $meta] = $this->filteredLogs($request, paginate: false);

        return view('asset_scans.print', array_merge($meta, [
            'logs' => $logs,
            'printedAt' => now(),
            'printedBy' => auth()->user(),
        ]));
    }

    public function resolve(AssetScanLog $assetScanLog)
    {
        abort_unless(auth()->user()->canHandleAssetScans(), 403);
        abort_unless($assetScanLog->isUnresolvedMismatch(), 422);

        $assetScanLog->update(['resolved_at' => now(), 'resolved_by' => auth()->id()]);

        return back()->with('success', 'Mismatch marked as resolved.');
    }

    /**
     * Only Super Admins may delete scan records -- this is historical/audit data, so it's
     * intentionally locked down to the highest privilege role rather than the general
     * Asset Management Admin role that otherwise manages this page.
     */
    public function destroy(AssetScanLog $assetScanLog)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $assetScanLog->delete();

        return back()->with('success', 'Scan record deleted.');
    }

    /**
     * Shared filter logic for both the on-screen monitoring table and the printable
     * report, so both always stay in sync with whatever filters the user applied.
     *
     * @return array{0: mixed, 1: array}
     */
    private function filteredLogs(Request $request, bool $paginate)
    {
        $filter = $request->get('filter', 'all');
        $floorFilter = trim((string) $request->get('floor', ''));
        $roomFilter = trim((string) $request->get('room', ''));

        $query = AssetScanLog::with(['item', 'user', 'resolver']);

        if ($filter === 'unresolved') {
            $query->where('status', 'mismatch')->whereNull('resolved_at');
        } elseif ($filter === 'resolved') {
            $query->where('status', 'mismatch')->whereNotNull('resolved_at');
        } elseif ($filter === 'matched') {
            $query->where('status', 'matched');
        }

        // Floor filter uses the real scanned_floor_id column (the floor the housekeeper
        // was standing in when they scanned), so it only ever narrows results to real,
        // structured floor data -- not a loose text match.
        if ($floorFilter !== '') {
            $query->where('scanned_floor_id', $floorFilter);
        }

        if ($roomFilter !== '') {
            $query->where(function ($q) use ($roomFilter) {
                $q->where('expected_room', $roomFilter)->orWhere('scanned_room', $roomFilter);
            });
        }

        $query->latest();
        $logs = $paginate ? $query->paginate(15)->withQueryString() : $query->limit(2000)->get();

        $unresolvedCount = AssetScanLog::where('status', 'mismatch')->whereNull('resolved_at')->count();
        $floors = Floor::orderBy('sort_order')->orderBy('name')->get();

        // Room dropdown narrows to the selected floor's rooms once a floor is chosen,
        // so the list isn't one giant flat list of every room in the building.
        $roomOptions = $floorFilter !== ''
            ? Room::where('floor_id', $floorFilter)->orderBy('name')->pluck('name')->unique()->values()
            : Room::orderBy('name')->pluck('name')->unique()->values();

        return [$logs, compact('filter', 'unresolvedCount', 'floors', 'floorFilter', 'roomOptions', 'roomFilter')];
    }
}
