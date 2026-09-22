@extends('layouts.admin', ['title' => 'Consumption Forecasting', 'subtitle' => "Predicts next month's OPEX consumption from your historical usage, using linear regression, so you know what to restock before you run out."])

@push('styles')
<style>
  /* ---------- Forecast: guided, at-a-glance layout (Modernist) ---------- */
  .fc-toolbar{display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap;padding:0 0 24px;margin-bottom:28px;background:transparent;border:0;border-bottom:2px solid var(--color-divider);border-radius:0;box-shadow:none}
  .fc-toolbar .fc-field{flex:1 1 320px;min-width:240px}
  .fc-toolbar .form-label{margin-bottom:6px}

  /* Result figures — the answer a user actually came for */
  .fc-result-grid{display:flex;flex-wrap:wrap;gap:1px;background:var(--color-neutral-300);border-top:2px solid var(--color-divider);border-bottom:2px solid var(--color-divider)}
  .fc-result{flex:1 1 240px;min-width:0;position:relative;overflow:visible;padding:28px 26px 30px;background:var(--color-bg);border:0;border-radius:0;box-shadow:none}
  .fc-result::before{display:none}
  .fc-result .fc-ico{display:none}
  .fc-result .fc-label{font-family:var(--font-heading);font-size:10.5px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--color-neutral-700)}
  .fc-result .fc-value{margin-top:18px;font-family:var(--font-heading);font-size:clamp(34px,3.4vw,48px);font-weight:800;line-height:1;letter-spacing:-.04em;color:var(--color-text)}
  .fc-result .fc-unit{font-size:15px;font-weight:600;letter-spacing:0;color:var(--color-neutral-700)}
  .fc-result .fc-sub{margin-top:12px;font-size:13px;line-height:1.45;color:var(--color-neutral-700)}
  .fc-acc-red .fc-value{color:var(--color-accent-700)}

  /* Collapsible math — kept for transparency, hidden by default */
  .calc-details{margin-top:28px;overflow:visible;background:transparent;border:0;border-top:2px solid var(--color-divider);border-radius:0;box-shadow:none}
  .calc-details>summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:14px;padding:20px 0;background:transparent;font-family:var(--font-heading);font-weight:800;font-size:18px;color:var(--color-text);user-select:none}
  .calc-details>summary::-webkit-details-marker{display:none}
  .calc-details>summary .chev{margin-left:auto;transition:transform .2s ease;color:var(--color-text)}
  .calc-details[open]>summary .chev{transform:rotate(180deg);color:var(--color-accent)}
  .calc-details>summary .form-section-icon{margin-bottom:0}
  .calc-body{padding:4px 0 24px}
  .calc-note{margin:0 0 16px;font-size:13px;line-height:1.5;color:var(--color-neutral-700)}

  /* Step guidance for empty / not-ready states */
  .fc-steps{display:flex;flex-wrap:wrap;gap:1px;margin-top:4px;background:var(--color-neutral-300);border:1px solid var(--color-neutral-300)}
  .fc-step{flex:1 1 220px;min-width:0;display:flex;gap:14px;align-items:flex-start;padding:20px;background:var(--color-bg);border:0;border-radius:0}
  .fc-step .fc-num{width:30px;height:30px;min-width:30px;border-radius:0;display:grid;place-items:center;background:var(--color-text);color:var(--color-bg);font-family:var(--font-heading);font-weight:800;font-size:12px}
  .fc-step h6{margin:0 0 4px;font-family:var(--font-heading);font-size:15px;font-weight:800;color:var(--color-text)}
  .fc-step p{margin:0;font-size:13px;line-height:1.45;color:var(--color-neutral-700)}

  @media (max-width:575.98px){.fc-result,.fc-step{flex-basis:100%}}
</style>
@endpush

@section('content')
@php
  $needRestock = collect($allForecasts)->filter(fn($r) => ($r['forecast']['suggestedRestock'] ?? 0) > 0)->count();
  $totalForecastable = count($allForecasts);
@endphp

{{-- ===== At-a-glance summary ===== --}}
<div class="stat-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
  <div class="stat-card">
    <div class="stat-icon icon-cyan"><i class="bi bi-boxes"></i></div>
    <div class="stat-label">Items with a forecast</div>
    <div class="stat-value">{{ $totalForecastable }}</div>
  </div>
  <div class="stat-card" style="{{ $needRestock > 0 ? '--stat-accent:var(--danger-ink)' : '--stat-accent:var(--success-ink)' }}">
    <div class="stat-icon {{ $needRestock > 0 ? 'icon-red' : 'icon-green' }}"><i class="bi bi-cart-plus"></i></div>
    <div class="stat-label">Items needing restock</div>
    <div class="stat-value">{{ $needRestock }}</div>
  </div>
</div>

{{-- ===== Overview table (the actionable view) ===== --}}
<div class="data-panel mb-3">
  <div class="section-icon-head">
    <div class="form-section-icon"><i class="bi bi-bar-chart-line"></i></div>
    <div>
      <h2 class="module-title" style="font-size:15px">Restock Priority</h2>
      <div class="module-note">Every OPEX item with enough history to forecast, most urgent first. Select one to see the full breakdown.</div>
    </div>
  </div>
  <div class="table-responsive mt-3">
    <table class="data-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Predicted next-month demand</th>
          <th>Current stock</th>
          <th>Suggested restock</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      @forelse($allForecasts as $row)
        <tr>
          <td data-label="Item"><strong>{{ $row['item']->item_code }}</strong> — {{ $row['item']->name }}</td>
          <td data-label="Predicted demand">{{ $row['forecast']['predicted'] }} {{ $row['item']->unit }}</td>
          <td data-label="Current stock">{{ $row['forecast']['currentStock'] }} {{ $row['item']->unit }}</td>
          <td data-label="Suggested restock">
            <span class="status {{ $row['forecast']['suggestedRestock'] > 0 ? 'low' : 'approved' }}">
              <i class="bi {{ $row['forecast']['suggestedRestock'] > 0 ? 'bi-exclamation-triangle' : 'bi-check-circle' }}"></i>
              {{ $row['forecast']['suggestedRestock'] > 0 ? $row['forecast']['suggestedRestock'].' '.$row['item']->unit : 'Stock OK' }}
            </span>
          </td>
          <td data-label=""><a href="{{ route('forecast.index', ['item_id' => $row['item']->id]) }}" class="btn-soft small-btn"><i class="bi bi-eye"></i> View details</a></td>
        </tr>
      @empty
        <tr><td colspan="5" class="empty-state"><i class="bi bi-inbox" style="font-size:26px;display:block;margin-bottom:8px;color:var(--ink-400)"></i>No OPEX items have enough history yet. You need at least two different calendar months of usage logged per item.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ===== Item picker ===== --}}
<form method="GET" class="fc-toolbar">
  <div class="fc-field">
    <label class="form-label"><i class="bi bi-search me-1"></i> Choose an item to forecast</label>
    <select name="item_id" class="form-select">
      @foreach($items as $item)
        <option value="{{ $item->id }}" @selected($selectedItem?->id === $item->id)>{{ $item->item_code }} — {{ $item->name }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <button class="btn-primaryx"><i class="bi bi-graph-up-arrow"></i> Compute forecast</button>
  </div>
</form>

@if($selectedItem)

<div class="module-head" style="margin-top:6px">
  <div>
    <h2 class="module-title" style="font-size:16px">{{ $selectedItem->item_code }} — {{ $selectedItem->name }}</h2>
    <div class="module-note">Forecast detail for the selected item.</div>
  </div>
</div>

@if($forecast && $forecast['ready'])
  {{-- ===== The answer: big readable result cards ===== --}}
  <div class="fc-result-grid mb-3">
    <div class="fc-result fc-acc-blue">
      <div class="fc-ico"><i class="bi bi-graph-up"></i></div>
      <div class="fc-label">Forecasted demand (next month)</div>
      <div class="fc-value">{{ $forecast['predicted'] }} <span class="fc-unit">{{ $selectedItem->unit }}</span></div>
      <div class="fc-sub">Projected consumption based on your usage trend.</div>
    </div>
    <div class="fc-result fc-acc-slate">
      <div class="fc-ico"><i class="bi bi-box-seam"></i></div>
      <div class="fc-label">Current stock on hand</div>
      <div class="fc-value">{{ $forecast['currentStock'] }} <span class="fc-unit">{{ $selectedItem->unit }}</span></div>
      <div class="fc-sub">What you have available right now.</div>
    </div>
    <div class="fc-result {{ $forecast['suggestedRestock'] > 0 ? 'fc-acc-red' : 'fc-acc-green' }}">
      <div class="fc-ico"><i class="bi {{ $forecast['suggestedRestock'] > 0 ? 'bi-cart-plus' : 'bi-check2-circle' }}"></i></div>
      <div class="fc-label">Suggested restock</div>
      <div class="fc-value">
        @if($forecast['suggestedRestock'] > 0){{ $forecast['suggestedRestock'] }} <span class="fc-unit">{{ $selectedItem->unit }}</span>@else Stock OK @endif
      </div>
      <div class="fc-sub">{{ $forecast['suggestedRestock'] > 0 ? 'Order this much to cover predicted demand.' : 'Your current stock covers the forecast.' }}</div>
    </div>
  </div>
@endif

{{-- ===== Log historical usage ===== --}}
@if($forecast)
<div class="surface p-3 mb-3">
  <div class="section-icon-head">
    <div class="form-section-icon"><i class="bi bi-journal-plus"></i></div>
    <div>
      <h2 class="module-title" style="font-size:15px">Log Historical Usage</h2>
      <div class="module-note">
        @if(!$forecast['ready'])
          The forecast needs at least two different calendar months of usage before it can spot a trend. Record past months' figures below to get started.
        @else
          Add another month anytime to keep the trend line current and improve accuracy.
        @endif
      </div>
    </div>
  </div>

  @if(!$forecast['ready'])
  <div class="fc-steps mb-3">
    <div class="fc-step"><div class="fc-num">1</div><div><h6>Pick a past month</h6><p>Choose any month you have usage figures for.</p></div></div>
    <div class="fc-step"><div class="fc-num">2</div><div><h6>Enter quantity used</h6><p>How much of this item was consumed that month.</p></div></div>
    <div class="fc-step"><div class="fc-num">3</div><div><h6>Add a second month</h6><p>Two different months unlock the forecast instantly.</p></div></div>
  </div>
  @endif

  <form method="POST" action="{{ route('forecast.usage-logs.store') }}" class="row g-3 align-items-end mt-1">
    @csrf
    <input type="hidden" name="item_id" value="{{ $selectedItem->id }}">
    <div class="col-md-4">
      <label class="form-label">Usage month</label>
      <input type="date" name="usage_date" class="form-control" max="{{ now()->toDateString() }}" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Quantity used</label>
      <input type="number" name="quantity_used" class="form-control" min="1" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Remarks (optional)</label>
      <input type="text" name="remarks" class="form-control" placeholder="e.g. April consumption">
    </div>
    <div class="col-md-2">
      <button class="btn-primaryx w-100"><i class="bi bi-plus-lg"></i> Add</button>
    </div>
  </form>
</div>
@endif

{{-- ===== Not-ready message ===== --}}
@if($forecast && !$forecast['ready'])
<div class="note-callout mb-3"><i class="bi bi-info-circle"></i><div>{{ $forecast['message'] }}</div></div>
@endif

{{-- ===== Calculation details (collapsible, for transparency) ===== --}}
@if($forecast && $forecast['ready'])
<details class="calc-details">
  <summary>
    <div class="form-section-icon"><i class="bi bi-calculator"></i></div>
    <div>
      <div>Calculation details</div>
      <div class="tiny" style="font-weight:500">Linear regression working — the math behind the forecast</div>
    </div>
    <i class="bi bi-chevron-down chev"></i>
  </summary>
  <div class="calc-body">
    <p class="calc-note">A best-fit line <strong>y = a + bx</strong> is fitted to your monthly usage, where <em>x</em> is the month index and <em>y</em> is the quantity used. The line is projected one month ahead to estimate demand.</p>

    <div class="row g-3">
      <div class="col-6 col-md-3"><div class="report-stat"><div class="tiny-2">Σx</div><div class="fs-4 fw-bold">{{ $forecast['sumX'] }}</div></div></div>
      <div class="col-6 col-md-3"><div class="report-stat"><div class="tiny-2">Σy</div><div class="fs-4 fw-bold">{{ $forecast['sumY'] }}</div></div></div>
      <div class="col-6 col-md-3"><div class="report-stat"><div class="tiny-2">Slope (b)</div><div class="fs-4 fw-bold">{{ $forecast['b'] }}</div></div></div>
      <div class="col-6 col-md-3"><div class="report-stat"><div class="tiny-2">Intercept (a)</div><div class="fs-4 fw-bold">{{ $forecast['a'] }}</div></div></div>
    </div>

    <div class="formula-card">y = {{ $forecast['a'] }} + {{ $forecast['b'] }}x&nbsp;&nbsp;→&nbsp;&nbsp;x = {{ $forecast['nextX'] }} gives {{ $forecast['predicted'] }} {{ $selectedItem->unit }}</div>

    <h6 style="font-size:12.5px;font-weight:700;color:var(--ink-700);margin:4px 0 8px"><i class="bi bi-table me-1"></i> Regression table</h6>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Month index (x)</th><th>Month</th><th>Usage (y)</th><th>x²</th><th>xy</th></tr></thead>
        <tbody>
        @forelse($forecast['points'] as $point)
          <tr>
            <td data-label="x">{{ $point['x'] }}</td>
            <td data-label="Month">{{ $point['period'] }}</td>
            <td data-label="Usage (y)">{{ $point['y'] }}</td>
            <td data-label="x²">{{ $point['x'] * $point['x'] }}</td>
            <td data-label="xy">{{ $point['x'] * $point['y'] }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="empty-state">No usage data yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</details>
@endif

{{-- ===== Recent usage log entries ===== --}}
<div class="data-panel mt-3">
  <div class="section-icon-head">
    <div class="form-section-icon"><i class="bi bi-clock-history"></i></div>
    <div>
      <h2 class="module-title" style="font-size:15px">Recent Usage Log Entries</h2>
      <div class="module-note">The individual records behind the monthly totals. @if(auth()->user()->isSuperAdmin())As Super Admin you can delete a wrong or duplicate entry — the forecast recalculates automatically.@else Only Super Admin accounts can delete entries here.@endif</div>
    </div>
  </div>
  <div class="table-responsive mt-3">
    <table class="data-table">
      <thead><tr><th>Date</th><th>Quantity used</th><th>Source</th><th>Remarks</th>@if(auth()->user()->isSuperAdmin())<th></th>@endif</tr></thead>
      <tbody>
      @forelse($usageLogs as $log)
        <tr>
          <td data-label="Date">{{ \Carbon\Carbon::parse($log->usage_date)->format('M d, Y') }}</td>
          <td data-label="Quantity used">{{ $log->quantity_used }} {{ $selectedItem->unit }}</td>
          <td data-label="Source"><span class="tag">{{ $log->source === 'manual_backfill' ? 'Manual entry' : 'Auto-logged' }}</span></td>
          <td data-label="Remarks">{{ $log->remarks ?: '—' }}</td>
          @if(auth()->user()->isSuperAdmin())
          <td data-label="">
            <form method="POST" action="{{ route('forecast.usage-logs.destroy', $log) }}" data-confirm="Delete this usage log entry? This will recalculate the forecast.">
              @csrf @method('DELETE')
              <button class="btn-soft small-btn text-danger"><i class="bi bi-trash"></i> Delete</button>
            </form>
          </td>
          @endif
        </tr>
      @empty
        <tr><td colspan="5" class="empty-state">No usage log entries yet for this item.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endif
@endsection
