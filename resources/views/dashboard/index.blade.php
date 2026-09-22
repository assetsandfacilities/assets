@extends('layouts.admin', [
    'title' => 'Dashboard',
    'pageHeading' => 'Welcome back, ' . (explode(' ', trim(auth()->user()->name))[0] ?? 'Admin'),
    'subtitle' => 'A live overview of inventory, requisitions and forecasting. Everything that needs a decision is on this screen.',
])

@section('page-actions')
<a href="{{ route('reports.index') }}" class="btn-soft"><i class="bi bi-graph-up"></i> Reports</a>
<a href="{{ route('requisitions.index') }}" class="btn-primaryx"><i class="bi bi-file-earmark-text"></i> Open requisitions</a>
@endsection

@if($pending > 0)
@section('poster')
<div class="poster-strip">
    <div class="poster-strip-text">{{ $pending }} {{ \Illuminate\Support\Str::plural('requisition', $pending) }} {{ $pending === 1 ? 'is' : 'are' }} still moving through approval.</div>
    <a class="poster-strip-btn" href="{{ route('requisitions.index') }}">Open the queue</a>
</div>
@endsection
@endif

@section('content')
<div class="fig-grid">
    <div class="fig">
        <div class="fig-label">Assets (CAPEX)</div>
        <div class="fig-value">{{ number_format($capexCount) }}</div>
        <div class="fig-note">{{ number_format($issuedAssets) }} issued asset {{ \Illuminate\Support\Str::plural('record', $issuedAssets) }}</div>
    </div>
    <div class="fig">
        <div class="fig-label">Consumables (OPEX)</div>
        <div class="fig-value">{{ number_format($opexCount) }}</div>
        <div class="fig-note">{{ number_format($forecastReadyItems) }} ready for forecasting</div>
    </div>
    <div class="fig">
        <div class="fig-label">Pending requisitions</div>
        <div class="fig-value">{{ number_format($pending) }}</div>
        <div class="fig-note">{{ number_format($approvedRequisitions) }} approved or finalized</div>
    </div>
    <div class="fig">
        <div class="fig-label">Low stock</div>
        <div class="fig-value">{{ number_format($lowStock) }}</div>
        <div class="fig-note">{{ $lowStock > 0 ? 'At or below threshold' : 'All consumables above threshold' }}</div>
    </div>
</div>

<div>
    <span class="section-kicker">One-click actions</span>
    <div class="quick-grid">
        <a class="quick-action" href="{{ route('requisitions.index') }}">Review requisitions<span>{{ $pending }} waiting in the approval route</span></a>
        <a class="quick-action" href="{{ route('forecast.index') }}">Log OPEX usage<span>Feeds next month's forecast</span></a>
        <a class="quick-action" href="{{ route('items.index', ['type' => 'OPEX', 'stock_filter' => 'low']) }}">Check limited stock<span>{{ $lowStock }} {{ \Illuminate\Support\Str::plural('item', $lowStock) }} to replenish</span></a>
        <a class="quick-action" href="{{ route('asset-scans.index') }}">Resolve scan mismatches<span>Reports from housekeeping</span></a>
        <a class="quick-action" href="{{ route('issuances.index') }}">Issue and return<span>Approved requests ready to release</span></a>
    </div>
</div>

<div class="data-panel">
    <div class="module-head">
        <div>
            <h2 class="module-title">OPEX budget utilisation by department</h2>
            <div class="module-note">Peso budget enforced on every charge slip: amount used against the department limit.</div>
        </div>
    </div>
    <div class="budget-rows">
        @forelse($budgetByDepartment as $row)
            @php
                $budgetShare = $row['limit'] > 0 ? min(100, round(($row['used'] / $row['limit']) * 100)) : 0;
            @endphp
            <div class="budget-row">
                <div class="budget-row-name">{{ $row['name'] }}</div>
                <div class="budget-row-bar" role="img" aria-label="{{ $budgetShare }}% of budget used"><span class="{{ $budgetShare >= 80 ? 'is-high' : '' }}" style="width:{{ $budgetShare }}%"></span></div>
                <div class="budget-row-amount">₱{{ number_format($row['used'], 2) }} / ₱{{ number_format($row['limit'], 2) }} · {{ $budgetShare }}%</div>
            </div>
        @empty
            <div class="empty-state">No departments have been set up yet.</div>
        @endforelse
    </div>
</div>

<div class="data-panel">
    <div class="module-head">
        <div>
            <h2 class="module-title">Forecast — top restock priorities</h2>
            <div class="module-note">Predicted next-month demand against stock on hand. {{ $forecastReadyItems }} forecast-ready OPEX {{ \Illuminate\Support\Str::plural('item', $forecastReadyItems) }}.</div>
        </div>
        <a href="{{ route('forecast.index') }}" class="btn-soft small-btn">Open forecasting tool</a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Item</th><th>Predicted next-month demand</th><th>Current stock</th><th>Suggested restock</th></tr></thead>
            <tbody>
            @forelse($forecastedItems as $row)
                <tr>
                    <td data-label="Item">
                        <a class="row-main" href="{{ route('forecast.index', ['item_id' => $row['item']->id]) }}">{{ $row['item']->name }}</a>
                        <div class="row-sub">{{ $row['item']->item_code }} · {{ $row['item']->unit }}</div>
                    </td>
                    <td data-label="Predicted">{{ $row['forecast']['predicted'] }} {{ $row['item']->unit }}</td>
                    <td data-label="Current stock">{{ $row['forecast']['currentStock'] }} {{ $row['item']->unit }}</td>
                    <td data-label="Suggested restock"><span class="status {{ $row['forecast']['suggestedRestock'] > 0 ? 'low' : 'approved' }}">{{ $row['forecast']['suggestedRestock'] > 0 ? '+' : '' }}{{ $row['forecast']['suggestedRestock'] }} {{ $row['item']->unit }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty-state">No OPEX items have enough historical usage data yet to forecast (need at least 2 different calendar months logged).</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel-grid-2">
    <div class="chart-card">
        <div class="chart-head">Inventory classification</div>
        <div class="chart-body">
            <div class="chart-note">CAPEX assets against OPEX consumables.</div>
            <div class="chart-wrap"><canvas id="inventoryTypeChart"></canvas></div>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-head">Asset category distribution</div>
        <div class="chart-body">
            <div class="chart-note">All inventory records grouped by category.</div>
            <div class="chart-wrap"><canvas id="categoryDistributionChart"></canvas></div>
        </div>
    </div>
</div>

<div class="chart-card">
    <div class="chart-head">Requisition trends</div>
    <div class="chart-body">
        <div class="chart-note">Requests submitted per month.</div>
        <div class="chart-wrap"><canvas id="requisitionTrendChart"></canvas></div>
    </div>
</div>

<div class="panel-grid-2">
    <div class="data-panel">
        <div class="module-head">
            <div>
                <h2 class="module-title">Low stock items</h2>
                <div class="module-note">Consumables that need replenishment soon.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Item</th><th>Category</th><th>Stock</th><th>Threshold</th></tr>
                </thead>
                <tbody>
                    @forelse($lowStockItems as $item)
                        <tr>
                            <td data-label="Name"><div class="row-main">{{ $item->name }}</div><div class="row-sub">{{ $item->item_code }}</div></td>
                            <td data-label="Category">{{ $item->category->name ?? 'Office Supplies' }}</td>
                            <td data-label="Stock"><span class="status low">{{ $item->quantity }}</span></td>
                            <td data-label="Threshold">{{ $item->low_stock_threshold }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">No low stock items at the moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="data-panel">
        <div class="module-head">
            <div>
                <h2 class="module-title">Recent requisitions</h2>
                <div class="module-note">Latest requests submitted in the system.</div>
            </div>
            <a href="{{ route('requisitions.index') }}" class="btn-soft small-btn">Open module</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Reference</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                @forelse($recentRequisitions as $req)
                    <tr>
                        <td data-label="Reference"><a class="row-main" href="{{ route('requisitions.show', $req) }}">{{ $req->requisition_no }}</a><div class="row-sub">{{ $req->user->name ?? 'Unknown User' }}</div></td>
                        <td data-label="Status"><span class="status {{ $req->status === 'approved' ? 'approved' : ($req->status === 'rejected' ? 'low' : 'pending') }}">{{ ucfirst($req->status) }}</span></td>
                        <td data-label="Date">{{ optional($req->requested_at)->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty-state">No recent requisitions yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// Chart palette follows the Modernist system: ink, red accent and the neutral ramp.
const palette = {
    ink: '#201e1d', accent: '#ec3013', accentDeep: '#ae1800', accentTint: '#ffc4b8',
    n800: '#444141', n600: '#7d7979', n400: '#bab6b6', n300: '#d7d3d3', bg: '#f3f2f2'
};
Chart.defaults.font.family = "'Archivo', system-ui, 'Segoe UI', Arial, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = palette.n800;
Chart.defaults.borderColor = palette.n300;

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { align: 'start', labels: { boxWidth: 12, boxHeight: 12, usePointStyle: true, pointStyle: 'rect' } } },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: palette.n300 }, border: { display: false } },
        x: { grid: { display: false }, border: { color: palette.ink } }
    }
};
new Chart(document.getElementById('inventoryTypeChart'), {
    type: 'bar',
    data: {
        labels: ['Inventory'],
        datasets: [
            { label: 'CAPEX (Assets)', data: [{{ $capexCount }}], backgroundColor: palette.ink, borderRadius: 0, maxBarThickness: 56 },
            { label: 'OPEX (Consumables)', data: [{{ $opexCount }}], backgroundColor: palette.accent, borderRadius: 0, maxBarThickness: 56 }
        ]
    },
    options: chartDefaults
});
new Chart(document.getElementById('requisitionTrendChart'), {
    type: 'line',
    data: {
        labels: {!! json_encode($requisitionTrend->pluck('month_num')->map(fn($m) => date('M', mktime(0,0,0,(int)$m,1)))) !!},
        datasets: [{
            label: 'Requests',
            data: {!! json_encode($requisitionTrend->pluck('total')) !!},
            borderColor: palette.ink,
            backgroundColor: 'rgba(32,30,29,.06)',
            pointBackgroundColor: palette.accent,
            pointBorderColor: palette.accent,
            pointStyle: 'rect',
            pointRadius: 5,
            pointHoverRadius: 7,
            tension: 0,
            fill: true,
            borderWidth: 2
        }]
    },
    options: chartDefaults
});
new Chart(document.getElementById('categoryDistributionChart'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($categoryDistribution->pluck('category_name')) !!},
        datasets: [{
            data: {!! json_encode($categoryDistribution->pluck('total')) !!},
            backgroundColor: [palette.ink, palette.accent, palette.n600, palette.accentTint, palette.n400, palette.accentDeep, palette.n800, palette.n300],
            borderColor: palette.bg,
            borderWidth: 2,
            hoverOffset: 4
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom', align: 'start', labels: { boxWidth: 12, boxHeight: 12, usePointStyle: true, pointStyle: 'rect', padding: 14 } } } }
});
</script>
@endpush
