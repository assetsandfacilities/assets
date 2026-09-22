<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventoryUsageLog;
use App\Models\Item;
use App\Support\ForecastCalculator;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->canManageInventory(), 403);
        $items = Item::where('item_type', 'OPEX')->orderBy('name')->get();
        $selectedItem = $request->filled('item_id') ? Item::find($request->integer('item_id')) : $items->first();
        $forecast = null;
        $usageLogs = collect();

        if ($selectedItem) {
            $points = ForecastCalculator::pointsFor($selectedItem->id);
            $forecast = ForecastCalculator::compute($points, (int) $selectedItem->quantity, (int) $selectedItem->low_stock_threshold);
            $usageLogs = InventoryUsageLog::where('item_id', $selectedItem->id)->orderByDesc('usage_date')->limit(15)->get();
        }

        // Quick overview of every OPEX item that already has enough data to
        // forecast, with the item name and predicted demand -- this is the
        // "list" view (separate from the single-item detail panel below it).
        $allForecasts = ForecastCalculator::allReadyForecasts();

        return view('forecast.index', compact('items', 'selectedItem', 'forecast', 'usageLogs', 'allForecasts'));
    }

    public function storeUsageLog(Request $request)
    {
        abort_unless(auth()->user()->canManageInventory(), 403);
        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'usage_date' => ['required', 'date', 'before_or_equal:today'],
            'quantity_used' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        InventoryUsageLog::create([
            'item_id' => $data['item_id'],
            'usage_date' => $data['usage_date'],
            'quantity_used' => $data['quantity_used'],
            'source' => 'manual_backfill',
            'remarks' => $data['remarks'] ?? 'Manually recorded historical usage',
        ]);

        return redirect()->route('forecast.index', ['item_id' => $data['item_id']])
            ->with('success', 'Historical usage record added. Forecast recalculated below.');
    }

    /**
     * Only Super Admins may delete a usage log entry -- this is the raw data that
     * feeds the forecast, so deleting the wrong entry changes what gets predicted.
     */
    public function destroyUsageLog(InventoryUsageLog $usageLog)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $itemId = $usageLog->item_id;
        $usageLog->delete();
        return redirect()->route('forecast.index', ['item_id' => $itemId])
            ->with('success', 'Usage log entry deleted. Forecast recalculated below.');
    }
}
