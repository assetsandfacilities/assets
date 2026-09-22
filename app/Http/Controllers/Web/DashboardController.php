<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Issuance;
use App\Models\Item;
use App\Models\Requisition;
use App\Models\RequisitionItem;

class DashboardController extends Controller
{
    public function index()
    {
        $lowStockItems = Item::where('item_type', 'OPEX')
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->orderBy('quantity')
            ->get();

        $capexCount = Item::where('item_type', 'CAPEX')->count();
        $opexCount = Item::where('item_type', 'OPEX')->count();
        $pending = Requisition::whereIn('status', ['pending_asset_management', 'pending_college_dean', 'pending_executive_director'])->count();
        $lowStock = $lowStockItems->count();

        /*
         | Budget utilisation, not allocation ceilings.
         |
         | This chart used to plot allocations.max_quantity, which is a policy
         | number that nothing on the web submission path actually enforces --
         | the real gate in RequisitionController@store is the department OPEX
         | budget. Showing the ceiling next to charts of live data invited the
         | reading that departments had already consumed those amounts.
         |
         | Every department is listed, including ones with no allocation row,
         | so the dashboard no longer looks like half the campus is missing.
         */
        $budgetByDepartment = \App\Models\Department::orderBy('name')->get()->map(function ($department) {
            $limit = (float) $department->opex_limit;
            $used = $department->opexConsumed();

            return [
                'name' => $department->name,
                'limit' => round($limit, 2),
                'used' => round($used, 2),
                // Never draw a negative bar: an over-budget department shows a
                // full "used" bar and zero remaining, and the overage is
                // readable from used vs limit in the tooltip.
                'remaining' => round(max($limit - $used, 0), 2),
            ];
        })->values();

        $requisitionTrend = Requisition::selectRaw(\App\Support\DateSql::monthNumSelect('requested_at') . ' as month_num')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('requested_at')
            ->groupByRaw(\App\Support\DateSql::monthNumGroupBy('requested_at'))
            ->orderBy('month_num')
            ->get();

        $categoryDistribution = Item::query()
            ->leftJoin('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->selectRaw("COALESCE(item_categories.name, 'Uncategorized') as category_name")
            ->selectRaw('COUNT(items.id) as total')
            ->groupBy('category_name')
            ->orderByDesc('total')
            ->get();

        $recentRequisitions = Requisition::with(['department','user','items.item'])->latest()->take(5)->get();
        $issuedAssets = Issuance::where('status', 'issued')->count();
        $approvedRequisitions = Requisition::whereIn('status', ['approved', 'partially_approved'])->count();

        // Forecast widget for the Dashboard -- shows item names + predicted demand,
        // not just a bare "ready" count like before.
        $allForecasts = collect(\App\Support\ForecastCalculator::allReadyForecasts());
        $forecastReadyItems = $allForecasts->count();
        $forecastedItems = $allForecasts->take(5);

        return view('dashboard.index', compact(
            'capexCount',
            'opexCount',
            'pending',
            'lowStock',
            'lowStockItems',
            'budgetByDepartment',
            'requisitionTrend',
            'categoryDistribution',
            'recentRequisitions',
            'issuedAssets',
            'approvedRequisitions',
            'forecastReadyItems',
            'forecastedItems'
        ));
    }
}
