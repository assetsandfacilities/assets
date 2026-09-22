@extends('layouts.admin', ['title' => 'Resource Allocation by Department', 'subtitle' => 'Set monthly request limits for CAPEX and OPEX'])
@section('page-actions')
<a href="{{ route('allocations.create') }}" class="btn-primaryx"><i class="bi bi-plus-lg"></i> New Allocation</a>
@endsection
@section('content')
<div class="surface p-3">
    <div class="table-responsive">
    <table class="data-table">
        <thead><tr><th>Department</th><th>Type</th><th>Max Quantity</th><th>Period</th><th>Progress</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
            @forelse($allocations as $allocation)
            <tr>
                <td><div style="font-weight:700">{{ $allocation->department->name ?? 'N/A' }}</div></td>
                <td>{!! $allocation->item_type === 'CAPEX' ? '<span class="code-badge">CAPEX</span>' : '<span class="pill-opex">OPEX</span>' !!}</td>
                <td>{{ $allocation->max_quantity }}</td>
                <td>{{ $allocation->period_label }}</td>
                <td><div class="stock-bar" style="width:120px"><div class="stock-fill" style="width: {{ min(100, $allocation->max_quantity * 8) }}%"></div></div></td>
                <td class="text-end"><a class="btn-soft small-btn" href="{{ route('allocations.edit',$allocation) }}"><i class="bi bi-pencil"></i></a><form class="d-inline" method="POST" action="{{ route('allocations.destroy',$allocation) }}">@csrf @method('DELETE')<button class="btn-soft small-btn"><i class="bi bi-three-dots-vertical"></i></button></form></td>
            </tr>
            @empty
            <tr><td colspan="6" class="empty-state">No allocations configured.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    {{ $allocations->links('vendor.pagination.custom') }}
</div>
@endsection