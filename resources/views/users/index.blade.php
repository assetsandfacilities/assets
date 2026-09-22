@extends('layouts.admin', ['title' => 'User Access Management', 'subtitle' => 'Manage user roles, account types, and department assignments.'])
@section('content')

@php
    $visibleUsers = $users->getCollection();
    $visibleActive = $visibleUsers->where('is_approved', true)->count();
    $visibleSignatures = $visibleUsers->filter(fn ($account) => $account->hasSignature())->count();
@endphp

<div class="user-admin-page">
    <section class="user-admin-hero">
        <div class="user-admin-hero-icon"><i class="bi bi-shield-lock"></i></div>
        <div class="user-admin-hero-copy">
            <div class="user-admin-eyebrow">Asset Management · Access Control</div>
            <h2>Manage accounts without the spreadsheet clutter.</h2>
            <p>Review identity, department, access role, account state, and stored e-signature from one clean workspace.</p>
        </div>
        <div class="user-admin-stats">
            <div class="user-mini-stat"><span>Visible</span><strong>{{ $visibleUsers->count() }}</strong></div>
            <div class="user-mini-stat"><span>Active</span><strong>{{ $visibleActive }}</strong></div>
            <div class="user-mini-stat"><span>Signed</span><strong>{{ $visibleSignatures }}</strong></div>
        </div>
    </section>

    <section class="user-command-bar">
        <form method="GET" class="user-command-search">
            <i class="bi bi-search"></i>
            <input class="search-input" name="search" value="{{ request('search') }}" placeholder="Search by name, email, or role...">
            @if(request('search'))
                <a class="user-clear-search" href="{{ route('users.index') }}" title="Clear search"><i class="bi bi-x-lg"></i></a>
            @endif
            <button class="btn-primaryx" type="submit"><i class="bi bi-funnel"></i> Filter accounts</button>
        </form>
        <div class="user-command-note"><i class="bi bi-info-circle"></i><span>Select <strong>Manage</strong> on an account to edit access settings or its e-signature.</span></div>
    </section>

    <section class="user-directory-shell">
        <div class="user-directory-head">
            <div>
                <span class="user-admin-eyebrow">Account Directory</span>
                <h3>Asset Management users</h3>
            </div>
            <div class="user-directory-count">{{ $users->total() }} total account{{ $users->total() === 1 ? '' : 's' }}</div>
        </div>

        <div class="user-card-grid">
            @forelse($users as $user)
                <article class="user-account-card">
                    <div class="user-card-topline {{ $user->is_approved ? 'is-active' : 'is-inactive' }}"></div>
                    <div class="user-card-main">
                        <div class="user-card-identity">
                            <div class="user-avatar-large">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                            <div class="user-card-nameblock">
                                <h4>{{ $user->name }}</h4>
                                <div class="user-email"><i class="bi bi-envelope"></i>{{ $user->email }}</div>
                            </div>
                            <span class="user-state-pill {{ $user->is_approved ? 'active' : 'inactive' }}">
                                <i class="bi {{ $user->is_approved ? 'bi-check-circle-fill' : 'bi-pause-circle-fill' }}"></i>
                                {{ $user->is_approved ? 'Active' : 'Deactivated' }}
                            </span>
                        </div>

                        <div class="user-card-facts">
                            <div class="user-fact">
                                <span>Department</span>
                                <strong><i class="bi bi-building"></i>{{ $user->department->name ?? 'Not assigned' }}</strong>
                            </div>
                            <div class="user-fact">
                                <span>Role</span>
                                <strong><i class="bi bi-person-badge"></i>{{ ucwords(str_replace('_',' ', $user->role)) }}</strong>
                            </div>
                            <div class="user-fact">
                                <span>Account type</span>
                                <strong><i class="bi bi-person-vcard"></i>{{ $user->accountTypeLabel() }}</strong>
                            </div>
                            <div class="user-fact">
                                <span>Approver type</span>
                                <strong><i class="bi bi-diagram-3"></i>{{ $user->approver_type ? ucwords(str_replace('_',' ', $user->approver_type)) : 'Not assigned' }}</strong>
                            </div>
                        </div>

                        <div class="user-card-health">
                            <div class="user-health-item {{ $user->email_verified_at ? 'ok' : 'warn' }}">
                                <i class="bi {{ $user->email_verified_at ? 'bi-patch-check-fill' : 'bi-exclamation-circle-fill' }}"></i>
                                <span>{{ $user->email_verified_at ? 'Email verified' : 'Email not verified' }}</span>
                            </div>
                            <div class="user-health-item {{ $user->hasSignature() ? 'ok' : 'warn' }}">
                                <i class="bi bi-pen-fill"></i>
                                <span>{{ $user->hasSignature() ? 'E-signature on file' : 'E-signature not set' }}</span>
                            </div>
                        </div>

                        <div class="user-card-actions">
                            <button class="user-manage-btn" type="button" data-bs-toggle="modal" data-bs-target="#manage-user-{{ $user->id }}">
                                <i class="bi bi-sliders2"></i> Manage account <i class="bi bi-box-arrow-up-right user-manage-chevron"></i>
                            </button>
                            @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline" data-confirm-title="Delete user account?" data-confirm="This account will no longer be able to sign in. This action cannot be undone." data-confirm-ok="Delete account" data-confirm-items="{{ json_encode([$user->display_name, $user->email]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="user-danger-icon" type="submit" title="Delete account"><i class="bi bi-trash3"></i></button>
                            </form>
                            @endif
                        </div>
                    </div>

                    <div class="modal fade user-management-modal" id="manage-user-{{ $user->id }}" tabindex="-1" aria-labelledby="manage-user-title-{{ $user->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header user-management-modal-header">
                                    <div>
                                        <span class="user-admin-eyebrow">Manage Account</span>
                                        <h3 class="modal-title" id="manage-user-title-{{ $user->id }}">{{ $user->display_name }}</h3>
                                        <div class="user-management-modal-subtitle">{{ $user->email }} · {{ ucwords(str_replace('_',' ', $user->role)) }}</div>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="user-management-panel">
                            <div class="user-panel-heading">
                                <div class="user-panel-heading-icon"><i class="bi bi-person-gear"></i></div>
                                <div><strong>Account configuration</strong><span>Changes here affect this account's access settings.</span></div>
                            </div>

                            <form method="POST" action="{{ route('users.update', $user) }}" class="row g-3 user-settings-form">
                                @csrf
                                @method('PUT')
                                <div class="col-xl-4 col-md-6">
                                    <label class="form-label">Name</label>
                                    <div class="d-flex gap-2">
                                        {{-- Polite title printed with the name on approved forms. --}}
                                        <select class="form-select" name="title" style="max-width:110px" aria-label="Polite title">
                                            <option value="">None</option>
                                            @foreach(\App\Models\User::politeTitles() as $politeTitle)
                                                <option value="{{ $politeTitle }}" @selected($user->title === $politeTitle)>{{ $politeTitle }}</option>
                                            @endforeach
                                        </select>
                                        <input class="form-control" name="name" value="{{ $user->name }}" required>
                                    </div>
                                    <div class="field-hint mt-1">Prints as <strong>{{ $user->display_name }}</strong> on signed documents.</div>
                                </div>
                                <div class="col-xl-4 col-md-6">
                                    <label class="form-label">Email <span class="user-readonly-tag">Locked</span></label>
                                    <input class="form-control" type="email" name="email" value="{{ $user->email }}" readonly aria-readonly="true">
                                </div>
                                <div class="col-xl-4 col-md-6">
                                    <label class="form-label">Role</label>
                                    <select class="form-select role-select" name="role" data-user-id="{{ $user->id }}">
                                        @foreach($roles as $role)
                                        <option value="{{ $role }}" @selected($user->role === $role)>{{ ucwords(str_replace('_',' ', $role)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl-4 col-md-6 approver-type-wrapper" id="approver-type-wrapper-{{ $user->id }}">
                                    <label class="form-label">Approver Type</label>
                                    <select class="form-select approver-type-select" name="approver_type">
                                        <option value="">None</option>
                                        @foreach($approverTypes as $type)
                                        <option value="{{ $type }}" @selected($user->approver_type === $type)>{{ ucwords(str_replace('_',' ', $type)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl-4 col-md-6 department-wrapper" id="department-wrapper-{{ $user->id }}">
                                    <label class="form-label">Department</label>
                                    <select class="form-select department-select" name="department_id">
                                        <option value="">No department</option>
                                        @foreach($departments as $department)
                                        <option value="{{ $department->id }}" @selected((int) $user->department_id === (int) $department->id)>{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="field-hint sdao-department-note" style="display:none">SDAO is school-wide and has no department.</div>
                                </div>
                                <div class="col-xl-4 col-md-6">
                                    <label class="form-label">Account Status</label>
                                    <select class="form-select" name="is_approved">
                                        <option value="0" @selected(!$user->is_approved)>Deactivated</option>
                                        <option value="1" @selected($user->is_approved)>Active</option>
                                    </select>
                                </div>
                                <div class="col-12 user-panel-save-row">
                                    <button class="btn-primaryx" type="submit"><i class="bi bi-check2-circle"></i> Save account settings</button>
                                </div>
                            </form>

                            @if(auth()->user()->isSuperAdmin())
                            <div class="user-signature-panel">
                                <div class="user-panel-heading mb-3">
                                    <div class="user-panel-heading-icon signature"><i class="bi bi-vector-pen"></i></div>
                                    <div><strong>E-Signature Management</strong><span>Only the Asset Management Super Admin can replace a stored e-signature.</span></div>
                                </div>
                                <form method="POST" action="{{ route('users.signature', $user) }}" enctype="multipart/form-data">
                                    @csrf
                                    @include('partials.signature-input', ['signatureId' => 'asset-user-signature-' . $user->id, 'existingSignature' => $user->signature_data, 'signatureRequired' => true])
                                    <button class="btn-primaryx mt-2" type="submit"><i class="bi bi-pen"></i> Replace E-Signature</button>
                                </form>

                                <div class="user-audit-panel">
                                    <div class="user-audit-title"><i class="bi bi-clock-history"></i> Signature change log</div>
                                    @forelse($user->signatureAudits as $audit)
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
                <div class="user-empty-card"><i class="bi bi-people"></i><strong>No users found</strong><span>Try changing your search keywords.</span></div>
            @endforelse
        </div>

        <div class="user-pagination-wrap">{{ $users->links('vendor.pagination.custom') }}</div>
    </section>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.role-select').forEach(function (roleSelect) {
        const userId = roleSelect.dataset.userId;
        const wrapper = document.getElementById('approver-type-wrapper-' + userId);
        if (!wrapper) return;

        const approverTypeSelect = wrapper.querySelector('.approver-type-select');
        const departmentWrapper = document.getElementById('department-wrapper-' + userId);
        const departmentSelect = departmentWrapper?.querySelector('.department-select');
        const sdaoNote = departmentWrapper?.querySelector('.sdao-department-note');

        function syncDepartmentForSdao() {
            const isSdao = roleSelect.value === 'approver' && approverTypeSelect?.value === 'sdao';
            if (departmentSelect) {
                departmentSelect.disabled = isSdao;
                if (isSdao) departmentSelect.value = '';
            }
            if (sdaoNote) sdaoNote.style.display = isSdao ? '' : 'none';
        }

        function toggleApproverType() {
            const isApprover = roleSelect.value === 'approver';
            wrapper.style.display = isApprover ? '' : 'none';
            if (!isApprover && approverTypeSelect) approverTypeSelect.value = '';
            syncDepartmentForSdao();
        }

        toggleApproverType();
        roleSelect.addEventListener('change', toggleApproverType);
        approverTypeSelect?.addEventListener('change', syncDepartmentForSdao);
    });
});
</script>
@endpush
@endsection
