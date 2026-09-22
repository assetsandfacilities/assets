<?php

namespace App\Support;

use App\Models\InventoryUsageLog;
use App\Models\Item;

/**
 * Linear Regression-based consumption forecasting, per the formula in the
 * capstone paper: y = a + bx, fitted by ordinary least squares over monthly
 * usage totals (x = month index, y = quantity used that month).
 *
 * Used by both ForecastController (single-item detail view) and
 * DashboardController (all-items summary list) so there is exactly one
 * implementation of the math -- no risk of the two screens disagreeing.
 */
class ForecastCalculator
{
    /**
     * Builds the {x, period, y} points for one item from its usage log,
     * grouped by calendar month (across all years).
     */
    public static function pointsFor(int $itemId): array
    {
        $rows = InventoryUsageLog::where('item_id', $itemId)
            ->selectRaw(DateSql::yearMonthSelect('usage_date') . ' as period, SUM(quantity_used) as usage_qty')
            ->groupByRaw(DateSql::yearMonthGroupBy('usage_date'))
            ->orderBy('period')
            ->get();

        $points = [];
        foreach ($rows as $i => $row) {
            $points[] = ['x' => $i + 1, 'period' => $row->period, 'y' => (int) $row->usage_qty];
        }
        return $points;
    }

    /**
     * Fits y = a + bx over the given points and projects one period ahead.
     * Returns ['ready' => false, ...] if there isn't enough data yet (needs
     * at least two distinct calendar months of usage).
     */
    public static function compute(array $points, int $currentStock, int $lowStockThreshold): array
    {
        $n = count($points);
        if ($n < 2) {
            return ['ready' => false, 'points' => $points, 'message' => 'At least two monthly usage records are needed to compute Linear Regression.'];
        }

        $sumX = array_sum(array_column($points, 'x'));
        $sumY = array_sum(array_column($points, 'y'));
        $sumX2 = array_sum(array_map(fn($p) => $p['x'] * $p['x'], $points));
        $sumXY = array_sum(array_map(fn($p) => $p['x'] * $p['y'], $points));
        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        $b = $denominator == 0 ? 0 : (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $a = ($sumY - ($b * $sumX)) / $n;
        $nextX = $n + 1;
        $predicted = max(0, round($a + ($b * $nextX)));
        $suggestedRestock = max(0, ($predicted + $lowStockThreshold) - $currentStock);

        return [
            'ready' => true,
            'points' => $points,
            'n' => $n,
            'sumX' => $sumX,
            'sumY' => $sumY,
            'sumX2' => $sumX2,
            'sumXY' => $sumXY,
            'a' => round($a, 2),
            'b' => round($b, 2),
            'nextX' => $nextX,
            'predicted' => $predicted,
            'currentStock' => $currentStock,
            'lowStockThreshold' => $lowStockThreshold,
            'suggestedRestock' => $suggestedRestock,
        ];
    }

    public static function forItem(Item $item): array
    {
        return self::compute(self::pointsFor($item->id), (int) $item->quantity, (int) $item->low_stock_threshold);
    }

    /**
     * Forecasts every OPEX item that has enough historical usage data (at
     * least two distinct calendar months logged). Used for the Dashboard's
     * "Forecasted Consumption" list and the Forecast page's overview table,
     * so item names actually show up instead of just a bare count.
     *
     * @return array<int, array{item: Item, forecast: array}>
     */
    public static function allReadyForecasts(): array
    {
        $results = [];
        foreach (Item::where('item_type', 'OPEX')->orderBy('name')->get() as $item) {
            $forecast = self::forItem($item);
            if ($forecast['ready']) {
                $results[] = ['item' => $item, 'forecast' => $forecast];
            }
        }
        // Items most urgently needing restock float to the top.
        usort($results, fn($a, $b) => $b['forecast']['suggestedRestock'] <=> $a['forecast']['suggestedRestock']);
        return $results;
    }
}
