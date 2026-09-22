@extends('layouts.admin', ['title' => 'Detailed Reports', 'subtitle' => 'Inventory, requisition, and issuance analytics'])
@section('content')

<div class="stat-grid">
    <div class="report-stat"><div class="tiny-2">CAPEX Assets</div><div class="stat-value">{{ $totals['assets'] }}</div></div>
    <div class="report-stat"><div class="tiny-2">OPEX Consumables</div><div class="stat-value">{{ $totals['consumables'] }}</div></div>
    <div class="report-stat"><div class="tiny-2">Total Requisitions</div><div class="stat-value">{{ $totals['requisitions'] }}</div></div>
    <div class="report-stat"><div class="tiny-2">Issued Records</div><div class="stat-value">{{ $totals['issued'] }}</div></div>
</div>

<div class="report-grid">
    <div class="report-box">
        <div class="chart-head"><i class="bi bi-boxes"></i> Inventory by Type</div>
        <div class="chart-body"><div class="chart-wrap"><canvas id="inventorySummaryChart"></canvas></div></div>
    </div>
    <div class="report-box">
        <div class="chart-head"><i class="bi bi-pie-chart"></i> Requisition Status Mix</div>
        <div class="chart-body"><div class="chart-wrap"><canvas id="requestStatusChart"></canvas></div></div>
    </div>
</div>

<div class="report-grid">
    <div class="report-box">
        <div class="chart-head"><i class="bi bi-building"></i> Requests by Department</div>
        <div class="chart-body"><div class="chart-wrap"><canvas id="departmentRequestChart"></canvas></div></div>
    </div>
    <div class="report-box">
        <div class="chart-head"><i class="bi bi-layers-half"></i> CAPEX Assets by Floor</div>
        <div class="chart-body"><div class="chart-wrap"><canvas id="assetsByFloorChart"></canvas></div></div>
    </div>
</div>

<div class="data-panel mb-3">
    <div class="module-head mb-0 report-accordion-toggle" data-bs-toggle="collapse" data-bs-target="#reportLowStock" role="button" aria-expanded="false" aria-controls="reportLowStock">
        <div>
            <h2 class="module-title">Low Stock Report</h2>
            <div class="module-note">Consumables below or equal to their stock threshold</div>
        </div>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse" id="reportLowStock">
    <div class="table-responsive mt-3">
        <table class="data-table">
            <thead>
                <tr><th>Item Code</th><th>Name</th><th>Category</th><th>Quantity</th><th>Threshold</th></tr>
            </thead>
            <tbody>
                @forelse($lowStockItems as $item)
                <tr>
                    <td data-label="Item Code">{{ $item->item_code }}</td>
                    <td data-label="Name">{{ $item->name }}</td>
                    <td data-label="Category">{{ $item->category->name ?? 'Office Supplies' }}</td>
                    <td data-label="Quantity"><span class="status low">{{ $item->quantity }}</span></td>
                    <td data-label="Threshold">{{ $item->low_stock_threshold }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">No low stock incidents recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</div>

<div class="data-panel mb-3">
    <div class="module-head mb-0 report-accordion-toggle" data-bs-toggle="collapse" data-bs-target="#reportForecast" role="button" aria-expanded="false" aria-controls="reportForecast">
        <div>
            <h2 class="module-title">Predictive Analytics for OPEX</h2>
            <div class="module-note">Forecasted next-term demand based on the most recent approved quantities.</div>
        </div>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse" id="reportForecast">
    <div class="table-responsive mt-3">
        <table class="data-table">
            <thead>
                <tr><th>Item</th><th>Recent Monthly Usage</th><th>Forecast Next Term (Linear Regression)</th><th>Current Stock</th><th>Action Insight</th></tr>
            </thead>
            <tbody>
                @forelse($forecastItems as $forecast)
                <tr>
                    <td data-label="Item">{{ $forecast['item_name'] }}</td>
                    <td data-label="Recent Monthly Usage">{{ $forecast['basis'] }}</td>
                    <td data-label="Forecast Next Term (Linear Regression)"><span class="status pending">{{ $forecast['forecast_next_term'] }} {{ $forecast['unit'] }}</span></td>
                    <td data-label="Current Stock">{{ $forecast['current_stock'] }} {{ $forecast['unit'] }}</td>
                    <td data-label="Action Insight">
                        @if($forecast['current_stock'] < $forecast['forecast_next_term'])
                            <span class="status low">Restock recommended</span>
                        @else
                            <span class="status approved">Stock is sufficient</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">Not enough approved requisition history yet to generate a forecast.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</div>


<div class="data-panel mb-3">
    <div class="module-head mb-0 report-accordion-toggle" data-bs-toggle="collapse" data-bs-target="#reportAssetLocation" role="button" aria-expanded="false" aria-controls="reportAssetLocation">
        <div>
            <h2 class="module-title">Asset Location Report</h2>
            <div class="module-note">CAPEX asset assignment by room or area</div>
        </div>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse" id="reportAssetLocation">
    <div class="table-responsive mt-3">
        <table class="data-table">
            <thead>
                <tr><th>Item Code</th><th>Asset Name</th><th>Category</th><th>Assigned Room</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($assetLocationReport as $asset)
                <tr>
                    <td data-label="Item Code">{{ $asset->item_code }}</td>
                    <td data-label="Asset Name">{{ $asset->name }}</td>
                    <td data-label="Category">{{ $asset->category->name ?? 'Uncategorized' }}</td>
                    <td data-label="Assigned Room">{{ $asset->room_assigned ?: 'Not assigned' }}</td>
                    <td data-label="Status">
                        @if($asset->room_assigned)
                            <span class="status approved">Trackable</span>
                        @else
                            <span class="status pending">Needs room assignment</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">No CAPEX assets available for location reporting.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</div>

<div class="data-panel mb-3">
    <div class="module-head mb-0 report-accordion-toggle" data-bs-toggle="collapse" data-bs-target="#reportApprovalTracking" role="button" aria-expanded="false" aria-controls="reportApprovalTracking">
        <div>
            <h2 class="module-title">Approval Tracking Report</h2>
            <div class="module-note">Recent requisitions with requestor, department, and approval status</div>
        </div>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse" id="reportApprovalTracking">
    <div class="table-responsive mt-3">
        <table class="data-table">
            <thead>
                <tr><th>Requisition No.</th><th>Requestor</th><th>Department</th><th>Requested At</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($approvalTracking as $record)
                <tr>
                    <td data-label="Requisition No.">{{ $record->requisition_no }}</td>
                    <td data-label="Requestor">{{ $record->user->name ?? 'Unknown' }}</td>
                    <td data-label="Department">{{ $record->department->name ?? 'Unassigned' }}</td>
                    <td data-label="Requested At">{{ optional($record->requested_at)->format('M d, Y h:i A') ?: 'N/A' }}</td>
                    <td data-label="Status"><span class="status pending">{{ $record->statusLabel() }}</span></td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">No requisition records available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</div>

<div class="data-panel mb-3">
    <div class="module-head mb-0 report-accordion-toggle" data-bs-toggle="collapse" data-bs-target="#reportBudgetDept" role="button" aria-expanded="false" aria-controls="reportBudgetDept">
        <div>
            <h2 class="module-title">OPEX Budget by Department</h2>
            <div class="module-note">Set by the Super Admin. Consumed is charged as soon as a requestor submits a charge slip (rejected requests don't count).</div>
        </div>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse" id="reportBudgetDept">
    <div class="table-responsive mt-3">
        <table class="data-table">
            <thead>
                <tr><th>Department</th><th>Department Budget</th><th>Consumed</th><th>Remaining</th></tr>
            </thead>
            <tbody>
                @forelse($budgetOverview as $row)
                <tr>
                    <td data-label="Department">{{ $row['name'] }}</td>
                    <td data-label="Department Budget">₱{{ number_format($row['limit'], 2) }}</td>
                    <td data-label="Consumed">₱{{ number_format($row['consumed'], 2) }}</td>
                    <td data-label="Remaining"><span class="status {{ $row['remaining'] <= 0 ? 'low' : 'approved' }}">₱{{ number_format($row['remaining'], 2) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="4" class="empty-state">No departments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</div>

<div class="data-panel mb-3">
    <div class="module-head mb-0 report-accordion-toggle" data-bs-toggle="collapse" data-bs-target="#reportBudgetLog" role="button" aria-expanded="false" aria-controls="reportBudgetLog">
        <div>
            <h2 class="module-title">Budget Consumption Log</h2>
            <div class="module-note">Who used department OPEX budget, what they bought, and where it was used. Most recent 50 line items.</div>
        </div>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse" id="reportBudgetLog">
    <div class="table-responsive mt-3">
        <table class="data-table">
            <thead>
                <tr><th>Date</th><th>Requisition No.</th><th>Requestor</th><th>Department</th><th>Item</th><th>Qty</th><th>Amount</th><th>Used For</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($budgetConsumptionLog as $line)
                <tr>
                    <td data-label="Date">{{ optional($line->requisition->requested_at)->format('M d, Y') ?: 'N/A' }}</td>
                    <td data-label="Requisition No.">{{ $line->requisition->requisition_no }}</td>
                    <td data-label="Requestor">{{ $line->requisition->user->name ?? 'Unknown' }}</td>
                    <td data-label="Department">{{ $line->requisition->department->name ?? 'Unassigned' }}</td>
                    <td data-label="Item">{{ $line->item->name ?? 'Deleted item' }} x{{ $line->quantity_requested }}</td>
                    <td data-label="Qty">{{ $line->quantity_requested }}</td>
                    <td data-label="Amount">₱{{ number_format($line->total_amount, 2) }}</td>
                    <td data-label="Used For">{{ $line->requisition->purpose ?: 'N/A' }}</td>
                    <td data-label="Status"><span class="status {{ str_contains($line->requisition->status,'approved') ? 'approved' : 'pending' }}">{{ $line->requisition->statusLabel() }}</span></td>
                </tr>
                @empty
                <tr><td colspan="9" class="empty-state">No budget consumption recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</div>

<div class="report-grid">
    <div class="report-box p-3">
        <h5 class="mb-2">Issuance Status Snapshot</h5>
        <div class="chart-wrap"><canvas id="issuanceStatusChart"></canvas></div>
    </div>
    <div class="report-box p-3 d-flex flex-column justify-content-between">
        <div>
            <h5 class="mb-2">Asset Mismatch Monitoring</h5>
            <div class="tiny mb-3">Open mismatches from mobile Floor/Room + QR scans that still need housekeeping follow-up.</div>
        </div>
        <div class="stat-value" style="color:{{ $unresolvedMismatches > 0 ? 'var(--danger-ink,#dd2b0f)' : 'var(--success-ink,#201e1d)' }}">{{ $unresolvedMismatches }}</div>
        <div class="tiny-2">{{ $unresolvedMismatches > 0 ? 'Unresolved mismatch(es) — see Scans for details.' : 'No open mismatches right now.' }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// Modernist palette: ink, red accent and the neutral ramp. The old key names
// are kept so each dataset below still reads the same way.
const palette = {
    navy: '#201e1d', gold: '#ec3013', cyan: '#7d7979', green: '#444141',
    coral: '#ffc4b8', lavender: '#bab6b6', ink: '#444141', line: '#d7d3d3', bg: '#f3f2f2'
};
Chart.defaults.font.family = "'Archivo', system-ui, 'Segoe UI', Arial, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = palette.ink;
Chart.defaults.borderColor = palette.line;

const baseOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { align: 'start', labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'rect' } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: palette.line } }, x: { grid: { display: false } } } };
const donutOptions = { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'rect', padding: 14 } } } };

new Chart(document.getElementById('inventorySummaryChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($inventoryByType->pluck('item_type')) !!},
        datasets: [{ label: 'Items', data: {!! json_encode($inventoryByType->pluck('total_items')) !!}, backgroundColor: [palette.navy, palette.gold], borderRadius: 0, maxBarThickness: 56 }]
    },
    options: baseOptions
});
new Chart(document.getElementById('requestStatusChart'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($requestStatus->pluck('status')) !!},
        datasets: [{ data: {!! json_encode($requestStatus->pluck('total')) !!}, backgroundColor: [palette.gold, palette.green, palette.cyan, palette.coral, palette.lavender], borderColor: palette.bg, borderWidth: 2, hoverOffset: 4 }]
    },
    options: donutOptions
});
new Chart(document.getElementById('departmentRequestChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($departmentRequests->pluck('name')) !!},
        datasets: [{ label: 'Requests', data: {!! json_encode($departmentRequests->pluck('total_requests')) !!}, backgroundColor: palette.cyan, borderRadius: 0, maxBarThickness: 40 }]
    },
    options: baseOptions
});
new Chart(document.getElementById('assetsByFloorChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($assetsByFloor->pluck('floor')) !!},
        datasets: [{ label: 'CAPEX Assets', data: {!! json_encode($assetsByFloor->pluck('total')) !!}, backgroundColor: palette.navy, borderRadius: 0, maxBarThickness: 40 }]
    },
    options: baseOptions
});
new Chart(document.getElementById('issuanceStatusChart'), {
    type: 'polarArea',
    data: {
        labels: {!! json_encode($issuanceStatus->pluck('status')) !!},
        datasets: [{ data: {!! json_encode($issuanceStatus->pluck('total')) !!}, backgroundColor: ['rgba(32,30,29,.85)','rgba(236,48,19,.8)','rgba(125,121,121,.75)','rgba(255,196,184,.9)'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'rect' } } }, scales: { r: { grid: { color: palette.line }, ticks: { display: false } } } }
});
</script>
@endpush
