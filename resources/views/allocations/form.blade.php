<div class="row g-3">
<div class="col-md-6"><label class="form-label">Department</label><select name="department_id" class="form-select" required>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id',$allocation->department_id ?? '')==$department->id)>{{ $department->name }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label">Item Type</label><select name="item_type" class="form-select"><option value="CAPEX" @selected(old('item_type',$allocation->item_type ?? '')=='CAPEX')>CAPEX</option><option value="OPEX" @selected(old('item_type',$allocation->item_type ?? '')=='OPEX')>OPEX</option></select></div>
<div class="col-md-6"><label class="form-label">Max Quantity</label><input type="number" name="max_quantity" class="form-control" value="{{ old('max_quantity',$allocation->max_quantity ?? 1) }}" required></div>
<div class="col-md-6"><label class="form-label">Period Label</label><input name="period_label" class="form-control" value="{{ old('period_label',$allocation->period_label ?? 'Monthly') }}" required></div>
</div>
