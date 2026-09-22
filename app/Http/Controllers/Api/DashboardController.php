<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Issuance;
use App\Models\Requisition;

class DashboardController extends Controller
{
    public function summary()
    {
        $lowStockCount = Item::where('item_type', 'OPEX')
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->count();

        return response()->json([
            'total_items' => Item::count(),
            'capex_items' => Item::where('item_type', 'CAPEX')->count(),
            'opex_items' => Item::where('item_type', 'OPEX')->count(),
            'pending_requisitions' => Requisition::where('status', 'pending')->count(),
            'issued_assets' => Issuance::where('status', 'issued')->count(),
            'low_stock_count' => $lowStockCount,
        ]);
    }
}
