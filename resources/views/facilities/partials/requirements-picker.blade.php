{{--
  "Other Items Needed and Services" picker.

  Every option comes from the facility_items table (managed by the FMO Super
  Admin) -- nothing is hard-coded here. Ticking a box reveals a quantity input
  so the requestor can say HOW MANY speakers / tables / ITSO personnel they
  need, and the "Others" box reveals a free-text field for anything not on the
  list.

  Expects: $catalogItems, $catalogServices (collections of FacilityItem).
--}}
@php
    $oldReq = old('requirements', []);
    $oldOtherNote = old('requirements_other_note');
    $oldOtherOn = old('requirements_other') || filled($oldOtherNote);
@endphp

<div class="col-12">
    <label class="form-label">Other Items Needed</label>
    <div class="module-note mb-2">Tick what you need, then type the quantity beside it.</div>
    @if($catalogItems->isEmpty())
        <div class="tiny text-muted">No items have been published by the Facilities Management Office yet.</div>
    @else
    <div class="req-grid">
        @foreach($catalogItems as $item)
            @php $on = !empty($oldReq[$item->id]['selected']); @endphp
            <div class="req-card {{ $on ? 'is-on' : '' }}" data-req-card>
                <label>
                    <input type="checkbox" data-req-check name="requirements[{{ $item->id }}][selected]" value="1" @checked($on)>
                    <span>{{ $item->name }}
                        @if($item->unit)<span class="req-unit">({{ $item->unit }})</span>@endif
                    </span>
                </label>
                @if($item->allows_quantity)
                    <input type="number" min="1" max="100000" class="req-qty" data-req-qty
                           name="requirements[{{ $item->id }}][quantity]"
                           value="{{ $oldReq[$item->id]['quantity'] ?? 1 }}"
                           aria-label="Quantity for {{ $item->name }}" {{ $on ? '' : 'hidden' }}>
                @endif
            </div>
        @endforeach
    </div>
    @endif
</div>

<div class="col-12">
    <label class="form-label">Facility Services Needed</label>
    <div class="module-note mb-2">For services, the quantity means how many personnel or units you are requesting.</div>
    @if($catalogServices->isEmpty())
        <div class="tiny text-muted">No services have been published by the Facilities Management Office yet.</div>
    @else
    <div class="req-grid">
        @foreach($catalogServices as $service)
            @php $on = !empty($oldReq[$service->id]['selected']); @endphp
            <div class="req-card {{ $on ? 'is-on' : '' }}" data-req-card>
                <label>
                    <input type="checkbox" data-req-check name="requirements[{{ $service->id }}][selected]" value="1" @checked($on)>
                    <span>{{ $service->name }}
                        @if($service->unit)<span class="req-unit">({{ $service->unit }})</span>@endif
                    </span>
                </label>
                @if($service->allows_quantity)
                    <input type="number" min="1" max="100000" class="req-qty" data-req-qty
                           name="requirements[{{ $service->id }}][quantity]"
                           value="{{ $oldReq[$service->id]['quantity'] ?? 1 }}"
                           aria-label="Quantity for {{ $service->name }}" {{ $on ? '' : 'hidden' }}>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    <div class="req-other-box">
        <label class="d-flex align-items-center gap-2 mb-0" style="font-size:12.5px;font-weight:700;cursor:pointer">
            <input type="checkbox" id="requirements_other" name="requirements_other" value="1" @checked($oldOtherOn)>
            Others (not on the list)
        </label>
        <div id="requirements_other_wrap" class="mt-2 {{ $oldOtherOn ? '' : 'd-none' }}">
            <textarea name="requirements_other_note" class="form-control" rows="3"
                      placeholder="Type here exactly what else you need, e.g. 2 standing fans, 1 tarpaulin stand, extra trash bins.">{{ $oldOtherNote }}</textarea>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // Quantity box only appears once its checkbox is ticked.
    document.querySelectorAll('[data-req-card]').forEach(function (card) {
        var check = card.querySelector('[data-req-check]');
        var qty = card.querySelector('[data-req-qty]');
        if (!check) return;
        function sync() {
            card.classList.toggle('is-on', check.checked);
            if (qty) {
                qty.hidden = !check.checked;
                if (check.checked && (!qty.value || parseInt(qty.value, 10) < 1)) { qty.value = 1; }
            }
        }
        check.addEventListener('change', sync);
        sync();
    });

    // "Others" reveals the free-text field.
    var other = document.getElementById('requirements_other');
    var wrap = document.getElementById('requirements_other_wrap');
    if (other && wrap) {
        function syncOther() {
            wrap.classList.toggle('d-none', !other.checked);
            if (!other.checked) { var t = wrap.querySelector('textarea'); if (t) { t.value = ''; } }
        }
        other.addEventListener('change', syncOther);
        syncOther();
    }
})();
</script>
@endpush
