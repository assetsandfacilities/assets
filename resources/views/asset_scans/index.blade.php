@extends('layouts.admin', ['title' => 'Mismatch Detection and Monitoring', 'subtitle' => "Scanning is performed exclusively through the mobile app by housekeeping/asset custodians (select Floor + Room, then scan the QR code). This page is a read-only monitoring view of everything they scan."])

@section('page-actions')
<a class="btn-soft small-btn" target="_blank" href="{{ route('asset-scans.print', request()->query()) }}"><i class="bi bi-printer"></i> Print</a>
@endsection

@section('content')


@if($unresolvedCount > 0)
<div class="alert alert-danger">{{ $unresolvedCount }} unresolved mismatch(es) — an asset was scanned somewhere other than its assigned room and hasn't been relocated/confirmed yet.</div>
@endif

<div class="page-tabs mb-3">
  <a href="{{ route('asset-scans.index', array_merge(request()->query(), ['filter' => 'all'])) }}" class="{{ $filter === 'all' ? 'active' : '' }}">All Scans</a>
  <a href="{{ route('asset-scans.index', array_merge(request()->query(), ['filter' => 'unresolved'])) }}" class="{{ $filter === 'unresolved' ? 'active' : '' }}">Unresolved Mismatches</a>
  <a href="{{ route('asset-scans.index', array_merge(request()->query(), ['filter' => 'resolved'])) }}" class="{{ $filter === 'resolved' ? 'active' : '' }}">Resolved</a>
  <a href="{{ route('asset-scans.index', array_merge(request()->query(), ['filter' => 'matched'])) }}" class="{{ $filter === 'matched' ? 'active' : '' }}">Matched</a>
</div>

<form method="GET" class="search-strip mb-3">
  <input type="hidden" name="filter" value="{{ $filter }}">
  <div class="filter-box"><i class="bi bi-building text-muted"></i><select name="floor" onchange="this.form.submit()">
    <option value="">All Floors</option>
    @foreach($floors as $floorOption)<option value="{{ $floorOption->id }}" @selected((string) $floorFilter === (string) $floorOption->id)>{{ $floorOption->name }}</option>@endforeach
  </select></div>
  <div class="filter-box"><i class="bi bi-door-open text-muted"></i><select name="room" onchange="this.form.submit()">
    <option value="">All Rooms</option>
    @foreach($roomOptions as $roomName)<option value="{{ $roomName }}" @selected($roomFilter === $roomName)>{{ $roomName }}</option>@endforeach
  </select></div>
  <button class="btn-primaryx small-btn" type="submit">Apply</button>
</form>

<div class="data-panel">
  <div class="table-responsive"><table class="data-table"><thead><tr><th>Date</th><th>Asset</th><th>Expected Room</th><th>Scanned Room</th><th>Status</th><th>Scanned By</th><th>Notes</th><th></th></tr></thead><tbody>
  @forelse($logs as $log)
    <tr>
      <td>{{ $log->created_at->format('M d, Y h:i A') }}</td>
      <td>{{ $log->item->item_code ?? 'N/A' }}<div class="tiny">{{ $log->item->name ?? '' }}</div></td>
      <td>{{ $log->expected_room ?: 'N/A' }}</td>
      <td>{{ $log->scanned_room ?: 'N/A' }}</td>
      <td>
        <span class="status {{ $log->status === 'matched' ? 'approved' : ($log->resolved_at ? 'pending' : 'low') }}">
          {{ $log->status === 'matched' ? 'Matched' : ($log->resolved_at ? 'Mismatch (Resolved)' : 'Mismatch (Open)') }}
        </span>
        @if($log->resolved_at)<div class="tiny">by {{ $log->resolver->name ?? 'N/A' }} on {{ $log->resolved_at->format('M d, h:i A') }}</div>@endif
      </td>
      <td>{{ $log->user->name ?? 'System' }}</td>
      <td>{{ $log->notes }}</td>
      <td class="table-actions">
        @if($log->isUnresolvedMismatch())
        <form method="POST" action="{{ route('asset-scans.resolve', $log) }}">@csrf
          <button class="btn-soft small-btn"><i class="bi bi-check-lg"></i> Mark Resolved</button>
        </form>
        @endif
        @if($canDelete)
        <form method="POST" action="{{ route('asset-scans.destroy', $log) }}" data-confirm="Delete this scan record? This cannot be undone.">
          @csrf @method('DELETE')
          <button class="btn-soft small-btn text-danger"><i class="bi bi-trash"></i> Delete</button>
        </form>
        @endif
      </td>
    </tr>
  @empty<tr><td colspan="8" class="empty-state">No scan logs yet. Scans will appear here once housekeeping starts scanning assets on mobile.</td></tr>@endforelse
  </tbody></table></div>
  <div class="mt-3">{{ $logs->links('vendor.pagination.custom') }}</div>
</div>
@endsection
