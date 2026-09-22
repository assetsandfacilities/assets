@extends('layouts.admin', ['title' => 'Charge Slip Request'])
@section('content')

<div class="premium-form-page">
    <div class="premium-form-intro premium-form-intro-compact">
        <div class="premium-form-intro-icon"><i class="bi bi-receipt-cutoff"></i></div>
        <div class="premium-form-intro-copy">
            <div class="premium-form-eyebrow">Asset Management</div>
            <h2>Charge Slip Request</h2>
            <p>Prepare the charge slip, add requested items, check the department OPEX balance, and route the request to the selected signatories.</p>
        </div>
        <div class="premium-form-intro-aside">
            <div class="premium-budget-card premium-budget-card-inline">
                <div class="premium-budget-icon"><i class="bi bi-wallet2"></i></div>
                <div class="premium-budget-copy">
                    <span>Department OPEX Availability</span>
                    <strong id="deptBudgetRemaining">₱0.00</strong>
                    <small>Remaining from <span id="deptBudgetLimit">₱0.00</span> allocated budget</small>
                </div>
            </div>
            <div class="premium-form-intro-badge"><i class="bi bi-arrow-right-circle"></i><span>Digital Routing</span></div>
        </div>
    </div>

    <form method="POST" action="{{ route('requisitions.store') }}" id="chargeSlipForm" class="premium-form">
        @csrf

        <section class="premium-section">
            <div class="premium-section-head">
                <div class="premium-step">01</div>
                <div>
                    <h3>Request Information</h3>
                    <p>Complete the charge slip reference, department, and request purpose.</p>
                </div>
            </div>
            <div class="premium-section-body">
                <div class="premium-document-strip">
                    <div>
                        <span class="premium-document-kicker">National University — Clark</span>
                        <strong>Charge Slip Form</strong>
                    </div>
                    <div class="premium-document-ref">
                        <label class="form-label">CSF No.</label>
                        <input type="text" name="csf_no" class="form-control" value="{{ old('csf_no') }}" placeholder="Optional reference number">
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label">Date</label>
                        <input type="text" class="form-control" value="{{ now()->format('F d, Y') }}" readonly>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label">Branch</label>
                        <input type="text" name="branch" class="form-control" value="{{ old('branch', 'NU Clark') }}" required>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Department / Office</label>
                        {{-- Panel revision: the department comes from the account, not from a
                             dropdown, because it is what decides the approval route. --}}
                        @if($routingDepartment)
                            <input type="text" class="form-control" value="{{ $routingDepartment->name }}" readonly>
                            <input type="hidden" name="department_id" id="departmentSelect" value="{{ $routingDepartment->id }}">
                            <div class="field-hint mt-1">Taken from your account. Ask the Super Admin if this is wrong.</div>
                        @else
                            <select name="department_id" id="departmentSelect" class="form-select" required>
                                <option value="">Select department</option>
                                @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <div class="field-hint mt-1">Your account has no department, so choose the one this charge slip is for. The approver is assigned from it automatically.</div>
                        @endif
                    </div>
                    <div class="col-12">
                        <label class="form-label">Purpose</label>
                        <input type="text" name="purpose" class="form-control" value="{{ old('purpose') }}" placeholder="e.g. Daily operation / enrollment" required>
                    </div>
                </div>
            </div>
        </section>

        <section class="premium-section">
            <div class="premium-section-head">
                <div class="premium-step">02</div>
                <div>
                    <h3>Requested Items</h3>
                    <p>Add one or more OPEX items. Unit, stock, unit price, and amount are calculated from the existing inventory data.</p>
                </div>
            </div>
            <div class="premium-section-body">
                <div class="premium-table-wrap table-responsive">
                    <table class="data-table premium-entry-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="min-width:240px">Item</th>
                                <th>Unit</th>
                                <th>Available</th>
                                <th style="min-width:100px">Quantity</th>
                                <th style="min-width:120px">Unit Price</th>
                                <th style="min-width:120px">Amount</th>
                                <th style="min-width:180px">Remarks</th>
                                <th class="premium-remove-col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $oldItems = old('items', [['item_id' => $selectedItemId, 'quantity_requested' => 1, 'remarks' => '']]);
                            @endphp
                            @foreach($oldItems as $index => $row)
                            <tr>
                                <td>
                                    <select name="items[{{ $index }}][item_id]" class="form-select item-select" required>
                                        <option value="">Select item</option>
                                        @foreach($items as $item)
                                        <option value="{{ $item->id }}" data-unit="{{ $item->unit }}" data-stock="{{ $item->quantity }}" data-cost="{{ number_format($item->latest_unit_cost ?? 0, 2, '.', '') }}" @selected(($row['item_id'] ?? null) == $item->id)>{{ $item->name }} ({{ $item->quantity }} {{ $item->unit }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" class="form-control unit-display" readonly></td>
                                <td><input type="text" class="form-control stock-display" readonly></td>
                                <td><input type="number" name="items[{{ $index }}][quantity_requested]" class="form-control qty-input" min="1" value="{{ $row['quantity_requested'] ?? 1 }}" required></td>
                                <td><input type="text" class="form-control unit-cost-display" readonly></td>
                                <td><input type="text" class="form-control amount-display" readonly></td>
                                <td><input type="text" name="items[{{ $index }}][remarks]" class="form-control" value="{{ $row['remarks'] ?? '' }}" placeholder="Optional note"></td>
                                <td><button type="button" class="premium-remove-row remove-row" aria-label="Remove item"><i class="bi bi-trash3"></i></button></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="premium-table-footer">
                    <button type="button" class="btn-soft small-btn premium-add-row" id="addRowBtn"><i class="bi bi-plus-lg"></i> Add Item Row</button>
                    <div class="premium-total-card">
                        <span>Estimated Total Amount</span>
                        <strong>₱<span id="grandTotal">0.00</span></strong>
                    </div>
                </div>

                <div id="budgetWarning" class="premium-warning mt-3" style="display:none">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div><strong>Department budget exceeded.</strong><span>Lower the quantities, remove an item, or ask the Super Admin to increase the department OPEX budget before submitting.</span></div>
                </div>
            </div>
        </section>

        <section class="premium-section">
            <div class="premium-section-head">
                <div class="premium-step">03</div>
                <div>
                    <h3>Signatories & Approval Routing</h3>
                    <p>The selected people are printed on the charge slip and receive the existing digital approval steps.</p>
                </div>
            </div>
            <div class="premium-section-body">
                <div class="premium-routing-grid premium-routing-grid-three">
                    <div class="premium-route-card">
                        <div class="premium-route-number">1</div>
                        <div class="premium-route-content">
                            <span class="premium-route-kicker">Requested By</span>
                            <strong>Requestor</strong>
                            <div class="premium-auto-assigned mt-3"><i class="bi bi-check-circle-fill"></i> From your account</div>
                            <div class="premium-locked-field mt-2">
                                <i class="bi bi-lock-fill"></i>
                                <span>{{ auth()->user()->name }}</span>
                            </div>
                            <p>Taken from the account you are signed in with. To correct the spelling, ask the Super Admin to update your account name.</p>
                        </div>
                    </div>

                    <div class="premium-route-card">
                        <div class="premium-route-number">2</div>
                        <div class="premium-route-content">
                            <span class="premium-route-kicker">Checked By</span>
                            <strong>College Dean / Program Chair</strong>
                            <div class="premium-auto-assigned mt-3"><i class="bi bi-check-circle-fill"></i> Auto Assigned</div>
                            {{-- Panel revision: assigned from the department above, shown for
                                 transparency but not editable. The server resolves it again on
                                 submit, so nothing here can redirect the approval. --}}
                            <div class="premium-locked-field mt-2" data-dean-name>{{ $deanApprover->name ?? 'No Dean assigned to this department yet' }}</div>
                            @if($deanApprover)
                                <p>Assigned from your department. The requestor does not choose who reviews the request.</p>
                            @else
                                <p class="text-danger">No active Dean is registered under this department, so this charge slip cannot be submitted yet. Please contact the Super Admin.</p>
                            @endif
                        </div>
                    </div>

                    <div class="premium-route-card">
                        <div class="premium-route-number">3</div>
                        <div class="premium-route-content">
                            <span class="premium-route-kicker">Approved By</span>
                            <strong>Executive Director</strong>
                            <div class="premium-auto-assigned mt-3"><i class="bi bi-check-circle-fill"></i> Auto Assigned</div>
                            @if($executiveDirector)
                                <div class="premium-locked-field mt-2">
                                    <i class="bi bi-person-badge-fill"></i>
                                    <span>{{ $executiveDirector->name }}</span>
                                </div>
                                <p>Campus-wide role. The Executive Director is assigned by the system, so there is nothing to choose here.</p>
                            @else
                                <div class="premium-warning mt-2">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <div><strong>No Executive Director configured.</strong><span>Ask the Super Admin to assign one before submitting this charge slip.</span></div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="premium-form-actionbar">
            <div class="premium-action-note"><i class="bi bi-info-circle"></i><span>All current inventory checks, OPEX validation, and approval routing stay exactly as implemented.</span></div>
            <div class="premium-action-buttons">
                <a href="{{ route('requisitions.index') }}" class="btn-soft">Cancel</a>
                <button class="btn-primaryx"><i class="bi bi-send"></i> Submit Charge Slip</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
/* Admin-without-a-department case only: show which approver the department maps
   to. The value is never submitted -- the server resolves the route itself. */
(function () {
    const deanMap = @json($deanByDepartment);
    const select = document.getElementById('departmentSelect');
    const label = document.querySelector('[data-dean-name]');
    if (!select || !label || select.tagName !== 'SELECT') return;
    function sync() {
        const entry = deanMap[select.value];
        label.textContent = entry ? entry.name : 'No Dean assigned to this department yet';
    }
    select.addEventListener('change', sync);
    sync();
})();
</script>
<script>
const tableBody = document.querySelector('#itemsTable tbody');
const addBtn = document.getElementById('addRowBtn');
const grandTotal = document.getElementById('grandTotal');
const departmentBudgets = @json($departmentBudgets);
const departmentSelect = document.getElementById('departmentSelect');
const deptBudgetRemaining = document.getElementById('deptBudgetRemaining');
const deptBudgetLimit = document.getElementById('deptBudgetLimit');
const budgetWarning = document.getElementById('budgetWarning');
const submitBtn = document.querySelector('#chargeSlipForm .btn-primaryx');

function currentDeptBudget() {
    return departmentBudgets[departmentSelect.value] || { limit: 0, remaining: 0 };
}

function refreshBudgetPanel() {
    const budget = currentDeptBudget();
    const total = [...tableBody.querySelectorAll('.amount-display')]
        .reduce((sum, input) => sum + Number((input.dataset.rawAmount || 0)), 0);
    deptBudgetRemaining.textContent = '₱' + formatCurrency(budget.remaining);
    deptBudgetLimit.textContent = '₱' + formatCurrency(budget.limit);
    const overBudget = total > budget.remaining;
    budgetWarning.style.display = overBudget ? 'block' : 'none';
    deptBudgetRemaining.style.color = overBudget ? '#C42A3B' : '';
    if (submitBtn) submitBtn.disabled = overBudget;
}

departmentSelect.addEventListener('change', refreshBudgetPanel);
const itemOptions = `{!! collect($items)->map(fn($item) => '<option value="'.$item->id.'" data-unit="'.$item->unit.'" data-stock="'.$item->quantity.'" data-cost="'.number_format($item->latest_unit_cost ?? 0, 2, '.', '').'">'.e($item->name).' ('.$item->quantity.' '.$item->unit.')</option>')->implode('') !!}`;

function formatCurrency(value) {
    const number = Number(value || 0);
    return number.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateGrandTotal() {
    const total = [...tableBody.querySelectorAll('.amount-display')]
        .reduce((sum, input) => sum + Number((input.dataset.rawAmount || 0)), 0);
    grandTotal.textContent = formatCurrency(total);
    refreshBudgetPanel();
}

function updateAmount(row) {
    const qty = Number(row.querySelector('.qty-input')?.value || 0);
    const cost = Number(row.querySelector('.item-select').selectedOptions[0]?.dataset.cost || 0);
    const amount = qty * cost;
    const amountInput = row.querySelector('.amount-display');
    amountInput.value = formatCurrency(amount);
    amountInput.dataset.rawAmount = amount;
    updateGrandTotal();
}

function refreshRow(row) {
    const selected = row.querySelector('.item-select').selectedOptions[0];
    row.querySelector('.unit-display').value = selected?.dataset.unit || '';
    row.querySelector('.stock-display').value = selected?.dataset.stock || '';
    row.querySelector('.unit-cost-display').value = formatCurrency(selected?.dataset.cost || 0);
    updateAmount(row);
}

function bindRow(row) {
    row.querySelector('.item-select').addEventListener('change', () => refreshRow(row));
    row.querySelector('.qty-input').addEventListener('input', () => updateAmount(row));
    row.querySelector('.remove-row').addEventListener('click', () => {
        if (tableBody.querySelectorAll('tr').length > 1) {
            row.remove();
            reindexRows();
            updateGrandTotal();
        }
    });
    refreshRow(row);
}

function reindexRows() {
    [...tableBody.querySelectorAll('tr')].forEach((row, index) => {
        row.querySelectorAll('select, input').forEach((input) => {
            if (input.name) {
                input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
            }
        });
    });
}

addBtn.addEventListener('click', () => {
    const index = tableBody.querySelectorAll('tr').length;
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><select name="items[${index}][item_id]" class="form-select item-select" required><option value="">Select item</option>${itemOptions}</select></td>
        <td><input type="text" class="form-control unit-display" readonly></td>
        <td><input type="text" class="form-control stock-display" readonly></td>
        <td><input type="number" name="items[${index}][quantity_requested]" class="form-control qty-input" min="1" value="1" required></td>
        <td><input type="text" class="form-control unit-cost-display" readonly></td>
        <td><input type="text" class="form-control amount-display" readonly></td>
        <td><input type="text" name="items[${index}][remarks]" class="form-control" placeholder="Optional"></td>
        <td><button type="button" class="premium-remove-row remove-row" aria-label="Remove item"><i class="bi bi-trash3"></i></button></td>`;
    tableBody.appendChild(row);
    bindRow(row);
});

document.querySelectorAll('#itemsTable tbody tr').forEach(bindRow);
</script>
@endpush
