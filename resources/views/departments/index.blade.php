@extends('layouts.admin', ['title' => 'Department Allocation', 'subtitle' => 'Manage department profiles and budget limits'])
@section('page-actions')
<a href="{{ route('departments.create') }}" class="btn-primaryx"><i class="bi bi-plus-lg"></i> Add Department</a>
@endsection
@section('content')
<div class="surface p-3">
    <div class="search-strip"><i class="bi bi-search text-muted"></i><input class="search-input" placeholder="Search departments..."><div class="filter-box"><i class="bi bi-funnel text-muted"></i><select><option>All</option><option>Over budget</option></select></div></div>
    <div class="table-responsive">
    <table class="data-table">
        <thead><tr><th>Department</th><th>Code</th><th>Department Budget</th><th>Consumed</th><th>Remaining</th><th>Users</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
            @forelse($departments as $department)
            <tr>
                <td><div style="font-weight:700">{{ $department->name }}</div><div class="tiny">Budget controls and request routing</div></td>
                <td>{{ $department->code }}</td>
                <td>₱{{ number_format($department->opex_limit, 2) }}</td>
                <td>₱{{ number_format($department->opex_consumed, 2) }}</td>
                <td><span class="status {{ $department->opex_remaining <= 0 ? 'low' : 'pending' }}">₱{{ number_format($department->opex_remaining, 2) }}</span></td>
                <td>{{ $department->users()->count() }}</td>
                <td class="text-end"><a class="btn-soft small-btn" href="{{ route('departments.edit',$department) }}"><i class="bi bi-pencil"></i></a><form class="d-inline" method="POST" action="{{ route('departments.destroy',$department) }}">@csrf @method('DELETE')<button class="btn-soft small-btn"><i class="bi bi-three-dots-vertical"></i></button></form></td>
            </tr>
            @empty
            <tr><td colspan="7" class="empty-state">No departments found.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    {{ $departments->links('vendor.pagination.custom') }}
</div>
@endsection