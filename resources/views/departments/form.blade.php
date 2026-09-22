<div class="row g-3">
<div class="col-md-6"><label class="form-label">Department Name</label><input name="name" class="form-control" value="{{ old('name',$department->name ?? '') }}" required></div>
<div class="col-md-6"><label class="form-label">Code</label><input name="code" class="form-control" value="{{ old('code',$department->code ?? '') }}" required></div>
{{--
    Only OPEX consumes department budget, so there is a single budget field.
    The underlying column is still named opex_limit -- renaming it would break
    the requisition and report queries that read it.
--}}
<div class="col-md-6">
    <label class="form-label">Department Budget (₱)</label>
    <input type="number" step="0.01" min="0" name="opex_limit" class="form-control" value="{{ old('opex_limit',$department->opex_limit ?? 0) }}" required>
    <div class="tiny mt-1">Total budget this department may consume through OPEX requisitions.</div>
    @isset($department)
    <div class="tiny mt-1">Consumed so far: ₱{{ number_format($department->opexConsumed(), 2) }} &middot; Remaining: ₱{{ number_format($department->opexRemaining(), 2) }}</div>
    @endisset
</div>
</div>
