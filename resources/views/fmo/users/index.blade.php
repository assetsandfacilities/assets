@extends('layouts.admin', ['title' => 'FMO User Management', 'subtitle' => 'Facilities-side accounts only. Asset Management users are not listed here.'])

@section('content')

@php
    $roleLabels = [
        'fmo_super_admin' => 'FMO Super Admin',
        'fmo' => 'FMO Staff',
        'housekeeping' => 'Housekeeping',
        'requestor' => 'Requestor',
    ];
    $visibleUsers = $users->getCollection();
    $visibleActive = $visibleUsers->where('is_approved', true)->count();
    $visibleApprovalAccounts = $visibleUsers->filter(fn ($account) => in_array($account->role, ['fmo_super_admin', 'fmo'], true))->count();
@endphp

<div class="user-admin-page fmo-user-admin-page">
    <section class="user-admin-hero">
        <div class="user-admin-hero-icon"><i class="bi bi-buildings"></i></div>
        <div class="user-admin-hero-copy">
            <div class="user-admin-eyebrow">Facilities Office · User Administration</div>
            <h2>Facilities accounts, organized around the people.</h2>
            <p>Create and maintain FMO approval accounts, housekeeping users, and registered requestors from a cleaner access-control workspace.</p>
        </div>
        <div class="user-admin-stats">
            <div class="user-mini-stat"><span>Visible</span><strong>{{ $visibleUsers->count() }}</strong></div>
            <div class="user-mini-stat"><span>Active</span><strong>{{ $visibleActive }}</strong></div>
            <div class="user-mini-stat"><span>Approvers</span><strong>{{ $visibleApprovalAccounts }}</strong></div>
        </div>
    </section>

    @if($pendingCount)
    <div class="user-pending-banner">
        <div class="user-pending-icon"><i class="bi bi-person-exclamation"></i></div>
        <div><strong>{{ $pendingCount }} account{{ $pendingCount === 1 ? '' : 's' }} waiting for review</strong><span>Check account details below before activating access.</span></div>
    </div>
    @endif

    <section class="user-create-shell">
        <div class="user-create-head">
            <div class="user-create-icon"><i class="bi bi-person-plus"></i></div>
            <div><span class="user-admin-eyebrow">Create Account</span><h3>Add a facilities user</h3><p>Requestor accounts still use the requestor registration flow.</p></div>
        </div>
        <form method="POST" action="{{ route('fmo.users.store') }}" class="user-create-form" enctype="multipart/form-data">@csrf
            <div class="row g-3">
                <div class="col-xl-4 col-md-6"><label class="form-label">Full Name</label><input name="name" class="form-control" value="{{ old('name') }}" placeholder="Enter user's full name" required></div>
                <div class="col-xl-4 col-md-6"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="name@nu-clark.edu.ph" required></div>
                <div class="col-xl-4 col-md-6">
                    <label class="form-label">Access Role</label>
                    <select name="role" id="newFmoRole" class="form-select" required>
                        @foreach($roles as $r)<option value="{{ $r }}" @selected(old('role') === $r)>{{ $roleLabels[$r] ?? ucwords(str_replace('_', ' ', $r)) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-xl-4 col-md-6">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">No department</option>
                        @foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-xl-4 col-md-6"><label class="form-label">Temporary Password</label><input type="password" name="password" class="form-control" data-pw-rules placeholder="Create password" required></div>
                <div class="col-xl-4 col-md-6"><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" placeholder="Repeat password" required></div>
            </div>
            <div id="newFmoSignatureSection" class="user-create-signature">
                <div class="user-inline-info"><i class="bi bi-vector-pen"></i><div><strong>Approval signature required</strong><span>FMO Staff and FMO Super Admin accounts need an e-signature because these roles can approve facility proposals.</span></div></div>
                @include('partials.signature-input', ['signatureId' => 'new-fmo-user-signature', 'signatureRequired' => true])
            </div>
            <div class="user-create-actions"><span><i class="bi bi-shield-check"></i> Access settings can still be changed later.</span><button class="btn-primaryx"><i class="bi bi-person-check"></i> Create account</button></div>
        </form>
    </section>

    <section class="user-directory-shell">
        <div class="user-directory-head user-directory-head-wrap">
            <div><span class="user-admin-eyebrow">Facilities Directory</span><h3>FMO users and requestors</h3></div>
            <div class="user-directory-count">{{ $users->total() }} total account{{ $users->total() === 1 ? '' : 's' }}</div>
        </div>

        <div class="user-filter-deck">
            <div class="user-role-filters">
                <a class="user-filter-chip {{ $role === '' ? 'active' : '' }}" href="{{ route('fmo.users.index', array_filter(['search' => $search])) }}"><i class="bi bi-collection"></i> All</a>
                @foreach($roles as $r)
                    <a class="user-filter-chip {{ $role === $r ? 'active' : '' }}" href="{{ route('fmo.users.index', array_filter(['role' => $r, 'search' => $search])) }}">{{ $roleLabels[$r] ?? ucwords(str_replace('_', ' ', $r)) }}</a>
                @endforeach
            </div>
            <form method="GET" class="user-command-search user-command-search-compact">
                <input type="hidden" name="role" value="{{ $role }}">
                <i class="bi bi-search"></i>
                <input class="search-input" name="search" value="{{ $search }}" placeholder="Search name, email, or role...">
                <button class="btn-primaryx" type="submit"><i class="bi bi-search"></i> Search</button>
            </form>
        </div>

        <div class="user-card-grid">
            @forelse($users as $u)
                <article class="user-account-card">
                    <div class="user-card-topline {{ $u->is_approved ? 'is-active' : 'is-inactive' }}"></div>
                    <div class="user-card-main">
                        <div class="user-card-identity">
                            <div class="user-avatar-large">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
                            <div class="user-card-nameblock"><h4>{{ $u->name }}</h4><div class="user-email"><i class="bi bi-envelope"></i>{{ $u->email }}</div></div>
                            <span class="user-state-pill {{ $u->is_approved ? 'active' : 'inactive' }}"><i class="bi {{ $u->is_approved ? 'bi-check-circle-fill' : 'bi-clock-fill' }}"></i>{{ $u->is_approved ? 'Active' : 'Pending Review' }}</span>
                        </div>

                        <div class="user-card-facts user-card-facts-three">
                            <div class="user-fact"><span>Department</span><strong><i class="bi bi-building"></i>{{ $u->department->name ?? 'Not assigned' }}</strong></div>
                            <div class="user-fact"><span>Role</span><strong><i class="bi bi-person-badge"></i>{{ $roleLabels[$u->role] ?? ucwords(str_replace('_', ' ', $u->role)) }}</strong></div>
                            <div class="user-fact"><span>E-Signature</span><strong><i class="bi bi-vector-pen"></i>{{ in_array($u->role, ['fmo_super_admin', 'fmo'], true) ? ($u->hasSignature() ? 'On file' : 'Not set') : 'Not required' }}</strong></div>
                        </div>

                        <div class="user-card-health">
                            <div class="user-health-item {{ $u->email_verified_at ? 'ok' : 'warn' }}"><i class="bi {{ $u->email_verified_at ? 'bi-patch-check-fill' : 'bi-exclamation-circle-fill' }}"></i><span>{{ $u->email_verified_at ? 'Email verified' : 'Email not verified' }}</span></div>
                            @if($u->signature_updated_at)<div class="user-health-item neutral"><i class="bi bi-clock-history"></i><span>Signature updated {{ $u->signature_updated_at->format('m/d/Y') }}</span></div>@endif
                        </div>

                        <div class="user-card-actions">
                            <button class="user-manage-btn" type="button" data-bs-toggle="modal" data-bs-target="#manage-fmo-user-{{ $u->id }}"><i class="bi bi-sliders2"></i> Manage account <i class="bi bi-box-arrow-up-right user-manage-chevron"></i></button>
                            @if($u->id !== auth()->id())
                            <form method="POST" action="{{ route('fmo.users.toggle', $u) }}" class="d-inline">@csrf
                                <button class="user-secondary-icon" type="submit" title="{{ $u->is_approved ? 'Deactivate account' : 'Activate account' }}"><i class="bi {{ $u->is_approved ? 'bi-pause-circle' : 'bi-play-circle' }}"></i></button>
                            </form>
                            <form method="POST" action="{{ route('fmo.users.destroy', $u) }}" class="d-inline" data-confirm-title="Delete FMO account?" data-confirm="This account will be permanently removed from Facilities Management access." data-confirm-ok="Delete account" data-confirm-items="{{ json_encode([$u->name, $u->email]) }}">@csrf @method('DELETE')
                                <button class="user-danger-icon" type="submit" title="Delete account"><i class="bi bi-trash3"></i></button>
                            </form>
                            @endif
                        </div>
                    </div>

                    <div class="modal fade user-management-modal" id="manage-fmo-user-{{ $u->id }}" tabindex="-1" aria-labelledby="manage-fmo-user-title-{{ $u->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header user-management-modal-header">
                                    <div>
                                        <span class="user-admin-eyebrow">Manage Account</span>
                                        <h3 class="modal-title" id="manage-fmo-user-title-{{ $u->id }}">{{ $u->name }}</h3>
                                        <div class="user-management-modal-subtitle">{{ $u->email }} · {{ $roleLabels[$u->role] ?? ucwords(str_replace('_', ' ', $u->role)) }}</div>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="user-management-panel">
                            <div class="user-panel-heading"><div class="user-panel-heading-icon"><i class="bi bi-person-gear"></i></div><div><strong>Account configuration</strong><span>Edit the profile, role, department, and account state.</span></div></div>
                            <form method="POST" action="{{ route('fmo.users.update', $u) }}" class="row g-3 user-settings-form">@csrf @method('PUT')
                                <div class="col-xl-4 col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ $u->name }}" required></div>
                                <div class="col-xl-4 col-md-6"><label class="form-label">Email <span class="user-readonly-tag">Locked</span></label><input class="form-control" value="{{ $u->email }}" readonly aria-readonly="true"></div>
                                <div class="col-xl-4 col-md-6">
                                    <label class="form-label">Role</label>
                                    @if($u->role === 'requestor')
                                        <input type="hidden" name="role" value="requestor">
                                        <input class="form-control" value="Requestor (self-registered)" readonly aria-readonly="true">
                                    @else
                                        <select class="form-select" name="role">@foreach($roles as $r)<option value="{{ $r }}" @selected($u->role === $r)>{{ $roleLabels[$r] ?? ucwords(str_replace('_', ' ', $r)) }}</option>@endforeach</select>
                                    @endif
                                </div>
                                <div class="col-xl-4 col-md-6"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value="">No department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((int) $u->department_id === (int) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
                                <div class="col-xl-4 col-md-6"><label class="form-label">Account Status</label><select class="form-select" name="is_approved"><option value="0" @selected(!$u->is_approved)>Deactivated</option><option value="1" @selected($u->is_approved)>Active</option></select></div>
                                <div class="col-12 user-panel-save-row"><button class="btn-primaryx"><i class="bi bi-check2-circle"></i> Save account settings</button></div>
                            </form>

                            <div class="user-security-panel">
                                <div class="user-panel-heading"><div class="user-panel-heading-icon security"><i class="bi bi-key"></i></div><div><strong>Password reset</strong><span>Set a new temporary password for this account.</span></div></div>
                                <form method="POST" action="{{ route('fmo.users.reset-password', $u) }}" class="row g-3">@csrf
                                    <div class="col-md-4"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" required></div>
                                    <div class="col-md-4"><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" required></div>
                                    <div class="col-md-4 d-flex align-items-end"><button class="btn-soft user-reset-btn"><i class="bi bi-key"></i> Reset password</button></div>
                                </form>
                            </div>

                            @if(in_array($u->role, ['fmo_super_admin', 'fmo'], true))
                            <div class="user-signature-panel">
                                <div class="user-panel-heading mb-3"><div class="user-panel-heading-icon signature"><i class="bi bi-vector-pen"></i></div><div><strong>E-Signature Management</strong><span>The FMO Super Admin can replace signatures for FMO approval accounts here.</span></div></div>
                                <form method="POST" action="{{ route('fmo.users.signature', $u) }}" enctype="multipart/form-data">@csrf
                                    @include('partials.signature-input', ['signatureId' => 'fmo-user-signature-' . $u->id, 'existingSignature' => $u->signature_data, 'signatureRequired' => true])
                                    <button class="btn-primaryx mt-2" type="submit"><i class="bi bi-pen"></i> Replace E-Signature</button>
                                </form>
                                <div class="user-audit-panel">
                                    <div class="user-audit-title"><i class="bi bi-clock-history"></i> Signature change log</div>
                                    @forelse($u->signatureAudits as $audit)
                                        <div class="user-audit-row">{{ optional($audit->changed_at)->format('m/d/Y h:i:s A') }} <span>changed by {{ $audit->changer->name ?? 'System' }}</span></div>
                                    @empty
                                        <div class="user-audit-empty">No signature replacements recorded yet.</div>
                                    @endforelse
                                </div>
                            </div>
                            @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="user-empty-card"><i class="bi bi-buildings"></i><strong>No facilities users found</strong><span>Change the active role filter or search keywords.</span></div>
            @endforelse
        </div>
        <div class="user-pagination-wrap">{{ $users->links('vendor.pagination.custom') }}</div>
    </section>

    <div class="user-scope-note"><i class="bi bi-shield-check"></i><div><strong>Facilities-only account scope</strong><span>Housekeeping is managed only here. Requestor cannot be assigned from the Role dropdown; self-registered Facilities requestors may still appear for account maintenance.</span></div></div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const role = document.getElementById('newFmoRole');
    const signatureSection = document.getElementById('newFmoSignatureSection');
    if (!role || !signatureSection) return;
    function syncSignatureRequirement() {
        signatureSection.style.display = ['fmo', 'fmo_super_admin'].includes(role.value) ? '' : 'none';
    }
    role.addEventListener('change', syncSignatureRequirement);
    syncSignatureRequirement();
});
</script>
@endpush

@endsection
