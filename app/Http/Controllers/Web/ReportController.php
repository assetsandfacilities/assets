<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssetScanLog;
use App\Models\Department;
use App\Models\Issuance;
use App\Models\Item;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $inventoryByType = Item::select('item_type', DB::raw('COUNT(*) as total_items'), DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('item_type')
            ->orderBy('item_type')
            ->get();

        $lowStockItems = Item::with('category')
            ->where('item_type', 'OPEX')
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->orderBy('quantity')
            ->take(10)
            ->get();

        $departmentRequests = Department::query()
            ->leftJoin('requisitions', 'departments.id', '=', 'requisitions.department_id')
            ->select('departments.name', DB::raw('COUNT(requisitions.id) as total_requests'))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total_requests')
            ->get();

        $requestStatus = Requisition::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        $issuanceStatus = Issuance::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        // Uses the same Linear Regression forecasting method described in the paper
        // (App\Support\ForecastCalculator) -- this used to be a separate 3-month
        // moving-average calculation, which contradicted the paper's methodology
        // (the paper explicitly chooses Linear Regression over simple averaging) and
        // could show a different predicted number than the Forecast page for the same
        // item. Both pages now compute from the exact same source.
        $forecastItems = collect(\App\Support\ForecastCalculator::allReadyForecasts())
            ->map(function ($row) {
                $item = $row['item'];
                $forecast = $row['forecast'];
                $recentUsage = collect($forecast['points'])->pluck('y')->take(-3);

                return [
                    'item_name' => $item->name,
                    'unit' => $item->unit,
                    'current_stock' => $item->quantity,
                    'forecast_next_term' => $forecast['predicted'],
                    'basis' => $recentUsage->implode(', '),
                ];
            })
            ->values();

        $assetLocationReport = Item::with('category')
            ->where('item_type', 'CAPEX')
            ->orderBy('room_assigned')
            ->orderBy('name')
            ->get();

        $approvalTracking = Requisition::with(['department', 'user'])
            ->latest('requested_at')
            ->take(10)
            ->get();

        $assetsByFloor = Item::where('item_type', 'CAPEX')
            ->whereNotNull('floor')
            ->select('floor', DB::raw('COUNT(*) as total'))
            ->groupBy('floor')
            ->orderBy('floor')
            ->get();

        $unresolvedMismatches = AssetScanLog::where('status', 'mismatch')->whereNull('resolved_at')->count();

        // OPEX budget overview per department, for Asset Management / Super Admin
        // to see at a glance how much of each department's budget is left.
        $budgetOverview = Department::orderBy('name')->get()->map(function ($department) {
            return [
                'name' => $department->name,
                'limit' => (float) $department->opex_limit,
                'consumed' => $department->opexConsumed(),
                'remaining' => $department->opexRemaining(),
            ];
        });

        // Who consumed budget, what was bought with it, and where it was used
        // (charge-to-budget-item / purpose / branch on the parent requisition) --
        // this is the audit trail Asset Management uses to monitor department
        // OPEX spend. Rejected requisitions never charged the budget, so they're
        // left out here too.
        $budgetConsumptionLog = RequisitionItem::query()
            ->with(['item', 'requisition.user', 'requisition.department'])
            ->whereHas('requisition', fn ($query) => $query->where('status', '!=', 'rejected'))
            ->whereHas('requisition.department')
            ->latest('id')
            ->take(50)
            ->get();

        $totals = [
            'assets' => Item::where('item_type', 'CAPEX')->count(),
            'consumables' => Item::where('item_type', 'OPEX')->count(),
            'requisitions' => Requisition::count(),
            'issued' => Issuance::where('status', 'issued')->count(),
        ];

        return view('reports.index', compact(
            'inventoryByType',
            'lowStockItems',
            'departmentRequests',
            'requestStatus',
            'issuanceStatus',
            'totals',
            'forecastItems',
            'assetLocationReport',
            'approvalTracking',
            'assetsByFloor',
            'unresolvedMismatches',
            'budgetOverview',
            'budgetConsumptionLog'
        ));
    }
}
