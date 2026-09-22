<input type="hidden" name="item_type" value="{{ old('item_type', $item->item_type ?? $type ?? request('type', 'CAPEX')) }}">
@php
    $fixedType = old('item_type', $item->item_type ?? $type ?? request('type', 'CAPEX'));
    $isCapex = $fixedType === 'CAPEX';
    $existingItem = ($item ?? null);
    $categoryTypeMap = collect($categories ?? [])->mapWithKeys(fn($cat) => [$cat->id => (($assetTypeOptions ?? [])[$cat->name] ?? [])]);
    $roomsByFloor = $roomOptions ?? [];
@endphp

<div class="inventory-studio-shell">
  <section class="inventory-panel inventory-panel-hero inventory-panel-identity">
    <div class="inventory-panel-head inventory-panel-head-compact">
      <div>
        <span class="inventory-panel-kicker">Step 01</span>
        <h3>{{ $isCapex ? 'Asset identity' : 'Item identity' }}</h3>
        <p>{{ $isCapex ? 'Review the fixed system information, then complete the fields used to identify the asset.' : 'Review the record type, then complete the fields used to identify this stock item.' }}</p>
      </div>
      <div class="inventory-step-chip">{{ $isCapex ? 'CAPEX' : 'OPEX' }}</div>
    </div>

    <div class="inventory-panel-body inventory-panel-body-identity">
      <div class="inventory-system-strip {{ $isCapex ? 'inventory-system-strip-capex' : 'inventory-system-strip-opex' }}">
        @if($isCapex)
          <div class="inventory-system-item">
            <div class="inventory-system-icon"><i class="bi bi-upc-scan"></i></div>
            <div>
              <span>Asset Tag ID</span>
              <strong>{{ $existingItem->item_code ?? 'Generated automatically on save' }}</strong>
              <small>System-generated from the selected floor and checked for duplicates.</small>
            </div>
          </div>
        @else
          <div class="inventory-system-item">
            <div class="inventory-system-icon"><i class="bi bi-hash"></i></div>
            <div>
              <span>Item Code</span>
              <strong>{{ old('item_code', $item->item_code ?? ($suggestedCode ?? 'Automatic if left blank')) ?: 'Automatic if left blank' }}</strong>
              <small>You can type a code below or let the system generate one.</small>
            </div>
          </div>
        @endif

        <div class="inventory-system-item">
          <div class="inventory-system-icon"><i class="bi bi-bookmark-check"></i></div>
          <div>
            <span>Record Type</span>
            <strong>{{ $fixedType }}</strong>
            <small>This value is fixed for the current module.</small>
          </div>
        </div>
      </div>

      <div class="inventory-entry-workspace">
        <div class="inventory-entry-card-head inventory-entry-card-head-spaced">
          <div class="inventory-entry-card-icon"><i class="bi bi-pencil-square"></i></div>
          <div>
            <strong>Details to fill in</strong>
            <span>Fields with a blue outline are editable. Gray system details above are informational only.</span>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-6">
            <div class="inventory-field">
              <label class="form-label">{{ $isCapex ? 'Asset Name / Model' : 'Name' }}</label>
              <input name="name" class="form-control" value="{{ old('name', $item->name ?? '') }}" placeholder="{{ $isCapex ? 'e.g. Dell OptiPlex 7090' : 'e.g. Bond Paper A4' }}" required>
              <div class="inventory-help">{{ $isCapex ? 'Use a complete asset name or model.' : 'Use the common item name that users will recognize.' }}</div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="inventory-field">
              <label class="form-label">Brand</label>
              <input name="brand" class="form-control" value="{{ old('brand', $item->brand ?? '') }}" placeholder="{{ $isCapex ? 'e.g. Dell, HP, Epson' : 'Optional brand' }}">
              <div class="inventory-help">Optional brand or manufacturer.</div>
            </div>
          </div>

          @if($isCapex)
            <div class="col-lg-8">
              <div class="inventory-field">
                <label class="form-label">Category &amp; Asset Type</label>
                <div class="tree-select inventory-tree-select" id="category_tree">
                  <button type="button" class="tree-select-trigger form-select">
                    <span class="tree-select-label">Select category</span>
                    <span class="chevron">&#9662;</span>
                  </button>
                  <div class="tree-select-panel"></div>
                </div>
                <input type="hidden" name="category_id" id="category_select" value="{{ old('category_id', $item->category_id ?? '') }}">
                <input type="hidden" name="asset_type_name" id="asset_type_choice" value="{{ old('asset_type_name', $item->asset_type_name ?? '') }}">
                <div class="inventory-help">Choose the category first, then the exact asset type.</div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="inventory-field">
                <label class="form-label">Date Acquired</label>
                <input type="date" name="acquisition_date" class="form-control" value="{{ old('acquisition_date', optional($item->acquisition_date ?? null)->format('Y-m-d') ?? '') }}">
                <div class="inventory-help">Optional acquisition date.</div>
              </div>
            </div>
          @else
            <div class="col-lg-6">
              <div class="inventory-field">
                <label class="form-label">Item Code</label>
                <input name="item_code" class="form-control" value="{{ old('item_code', $item->item_code ?? ($suggestedCode ?? '')) }}" placeholder="Leave blank to auto-generate">
                <div class="inventory-help">Optional. Leave blank for automatic code generation.</div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="inventory-field">
                <label class="form-label">Category</label>
                <div class="tree-select inventory-tree-select" id="category_tree_opex">
                  <button type="button" class="tree-select-trigger form-select">
                    <span class="tree-select-label">Select category</span>
                    <span class="chevron">&#9662;</span>
                  </button>
                  <div class="tree-select-panel"></div>
                </div>
                <input type="hidden" name="category_id" id="category_select" value="{{ old('category_id', $item->category_id ?? '') }}">
                <div class="inventory-help">Choose the most appropriate stock category.</div>
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>
  </section>

  <section class="inventory-panel">
    <div class="inventory-panel-head">
      <div>
        <span class="inventory-panel-kicker">Step 02</span>
        <h3>{{ $isCapex ? 'Placement, ownership & quantity' : 'Stock, pricing & assignment' }}</h3>
        <p>{{ $isCapex ? 'Set the department, location, and number of separate asset records to create.' : 'Set the assigned department, stock quantity, unit, price, availability, and low-stock threshold.' }}</p>
      </div>
      <div class="inventory-step-bullet">02</div>
    </div>

    <div class="inventory-panel-body">
      @if($isCapex)
        <input type="hidden" name="quantity" value="{{ old('quantity', $item->quantity ?? 1) }}">
        <input type="hidden" name="unit" value="{{ old('unit', $item->unit ?? 'unit') }}">
        <input type="hidden" name="unit_price" value="{{ old('unit_price', $item->unit_price ?? 0) }}">
        <input type="hidden" name="availability_status" value="{{ old('availability_status', $item->availability_status ?? 'Available') }}">
        <input type="hidden" name="low_stock_threshold" value="{{ old('low_stock_threshold', $item->low_stock_threshold ?? 0) }}">

        <div class="inventory-step-grid inventory-step-grid-capex">
          <div class="inventory-field-card">
            <div class="inventory-subhead">
              <i class="bi bi-building"></i>
              <div><strong>Department assignment</strong><span>Choose the department that uses or owns this asset.</span></div>
            </div>
            <div class="inventory-field mt-3">
              <label class="form-label">Assigned Department</label>
              <select name="assigned_department_id" class="form-select">
                <option value="">Select department</option>
                @foreach(($departments ?? []) as $department)
                  <option value="{{ $department->id }}" @selected(old('assigned_department_id', $item->assigned_department_id ?? '') == $department->id)>{{ $department->name }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="inventory-field-card">
            <div class="inventory-subhead">
              <i class="bi bi-geo-alt"></i>
              <div><strong>Storage location</strong><span>Select the floor and room used for the asset tag/location record.</span></div>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <div class="inventory-field">
                  <label class="form-label">Floor</label>
                  @if($existingItem?->exists)
                    <input class="form-control" value="{{ $existingItem->floorRef->name ?? $existingItem->floor }}" readonly disabled>
                    <input type="hidden" name="floor_id" id="floor_select" value="{{ $existingItem->floor_id }}">
                    <div class="inventory-help">Floor is locked after creation.</div>
                  @else
                    <div class="tree-select inventory-tree-select" id="floor_tree">
                      <button type="button" class="tree-select-trigger form-select">
                        <span class="tree-select-label">Select floor</span>
                        <span class="chevron">&#9662;</span>
                      </button>
                      <div class="tree-select-panel"></div>
                    </div>
                    <input type="hidden" name="floor_id" id="floor_select" value="{{ old('floor_id') }}">
                    <input type="hidden" name="room_id" id="room_select" value="{{ old('room_id') }}">
                    <div class="inventory-help">Select a floor, then a room under it.</div>
                  @endif
                </div>
              </div>

              @if($existingItem?->exists)
                <div class="col-md-6" id="room_wrap">
                  <div class="inventory-field">
                    <label class="form-label">Assigned Room</label>
                    <select name="room_id" id="room_select_edit" class="form-select" required><option value="">Select room</option></select>
                  </div>
                </div>
              @else
                <div class="col-md-6">
                  <div class="inventory-field">
                    <label class="form-label">How Many Units?</label>
                    <input type="number" name="unit_count" class="form-control" min="1" max="300" value="{{ old('unit_count', 1) }}" required>
                    <div class="inventory-help">Creates separate asset records with unique tags.</div>
                  </div>
                </div>
              @endif
            </div>
          </div>
        </div>

        <div class="inventory-reference-note mt-3"><i class="bi bi-info-circle"></i><span>Missing a category, asset type, floor, or room? Add it first under Reference Data.</span></div>
      @else
        <div class="inventory-step-grid inventory-step-grid-opex">
          <div class="inventory-field-card">
            <div class="inventory-subhead"><i class="bi bi-building"></i><div><strong>Department & availability</strong><span>Choose the assigned department and current stock status.</span></div></div>
            <div class="row g-3 mt-1">
              <div class="col-12"><div class="inventory-field"><label class="form-label">Assigned Department</label><select name="assigned_department_id" class="form-select"><option value="">Select department</option>@foreach(($departments ?? []) as $department)<option value="{{ $department->id }}" @selected(old('assigned_department_id', $item->assigned_department_id ?? '') == $department->id)>{{ $department->name }}</option>@endforeach</select></div></div>
              <div class="col-12"><div class="inventory-field"><label class="form-label">Availability</label><select name="availability_status" class="form-select"><option value="Available" @selected(old('availability_status', $item->availability_status ?? 'Available')==='Available')>Available</option><option value="Limited Stock" @selected(old('availability_status', $item->availability_status ?? '')==='Limited Stock')>Limited Stock</option><option value="Out of Stock" @selected(old('availability_status', $item->availability_status ?? '')==='Out of Stock')>Out of Stock</option></select></div></div>
            </div>
          </div>

          <div class="inventory-field-card">
            <div class="inventory-subhead"><i class="bi bi-box-seam"></i><div><strong>Stock & pricing</strong><span>Set the current quantity, unit, price, and alert threshold.</span></div></div>
            <div class="row g-3 mt-1">
              <div class="col-md-6"><div class="inventory-field"><label class="form-label">Quantity</label><input type="number" name="quantity" class="form-control" value="{{ old('quantity', $item->quantity ?? 0) }}" required></div></div>
              <div class="col-md-6"><div class="inventory-field"><label class="form-label">Unit</label><input name="unit" class="form-control" value="{{ old('unit', $item->unit ?? '') }}" placeholder="e.g. pack, box, ream" required></div></div>
              <div class="col-md-6"><div class="inventory-field"><label class="form-label">Unit Price</label><input type="number" step="0.01" min="0" name="unit_price" class="form-control" value="{{ old('unit_price', $item->unit_price ?? 0) }}"></div></div>
              <div class="col-md-6"><div class="inventory-field"><label class="form-label">Low Stock Threshold</label><input type="number" name="low_stock_threshold" class="form-control" value="{{ old('low_stock_threshold', $item->low_stock_threshold ?? 0) }}" required></div></div>
            </div>
          </div>
        </div>
      @endif
    </div>
  </section>

  <section class="inventory-panel">
    <div class="inventory-panel-head">
      <div>
        <span class="inventory-panel-kicker">Step 03</span>
        <h3>Media, description & status</h3>
        <p>Attach an image and add optional specifications or notes before saving.</p>
      </div>
      <div class="inventory-step-bullet">03</div>
    </div>

    <div class="inventory-panel-body">
      <div class="inventory-media-grid">
        <div class="inventory-field-card">
          <div class="inventory-subhead"><i class="bi bi-image"></i><div><strong>Image</strong><span>JPG, PNG, or WEBP up to 4MB.</span></div></div>
          <div class="inventory-field mt-3"><label class="form-label">Item Image</label><input type="file" name="image_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*"></div>
          @if(!empty($item?->display_image))
            <div class="inventory-current-image mt-3"><span class="inventory-current-image-label">Current image</span><img src="{{ $item->display_image }}" alt="{{ $item->name ?? 'Item image' }}">@if(!empty($item?->image_path))<div class="form-check mt-3"><input class="form-check-input" type="checkbox" value="1" name="remove_image" id="remove_image"><label class="form-check-label" for="remove_image">Remove current image</label></div>@endif</div>
          @endif
        </div>

        <div class="inventory-field-card">
          <div class="inventory-subhead"><i class="bi bi-card-text"></i><div><strong>Specifications & description</strong><span>Add useful technical details or internal notes.</span></div></div>
          <div class="row g-3 mt-1">
            <div class="col-12"><div class="inventory-field"><label class="form-label">Specifications</label><textarea name="specifications" class="form-control" rows="3" placeholder="Optional specifications or model details">{{ old('specifications', $item->specifications ?? '') }}</textarea></div></div>
            <div class="col-12"><div class="inventory-field"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" placeholder="Optional description or internal notes">{{ old('description', $item->description ?? '') }}</textarea></div></div>
            <div class="col-12"><div class="inventory-status-row form-check"><input class="form-check-input" type="checkbox" value="1" name="is_active" id="is_active" @checked(old('is_active', $item->is_active ?? true))><label class="form-check-label" for="is_active">Keep this record active and available in the system</label></div></div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
(function () {
  const categoryTypeMap = @json($categoryTypeMap);
  const roomsByFloor = @json($roomsByFloor);
  const categoryList = @json($categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values());
  const floorList = @json(collect($floors ?? [])->map(fn($f) => ['id' => $f->id, 'name' => $f->name])->values());

  const presetCategoryId = @json(old('category_id', $item->category_id ?? ''));
  const presetAssetType = @json(old('asset_type_name', $item->asset_type_name ?? ''));
  const presetFloorId = @json(old('floor_id', $existingItem?->floor_id ?? ''));
  const presetRoomId = @json(old('room_id', $item->room_id ?? ''));

  function initTreeSelect(rootEl, groups, opts) {
    const trigger = rootEl.querySelector('.tree-select-trigger');
    const labelEl = trigger.querySelector('.tree-select-label');
    const panel = rootEl.querySelector('.tree-select-panel');

    function render() {
      panel.innerHTML = '';
      if (!groups.length) {
        panel.innerHTML = '<div class="tree-select-empty">' + (opts.emptyText || 'Nothing set up yet.') + '</div>';
        return;
      }
      if (opts.flat) {
        groups.forEach(function (group) {
          const leafEl = document.createElement('div');
          leafEl.className = 'tree-select-leaf';
          leafEl.textContent = group.name;
          if (opts.selectedGroupId !== undefined && String(opts.selectedGroupId) === String(group.id)) {
            leafEl.classList.add('selected');
            labelEl.textContent = group.name;
          }
          leafEl.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.querySelectorAll('.tree-select-leaf').forEach(l => l.classList.remove('selected'));
            leafEl.classList.add('selected');
            labelEl.textContent = group.name;
            rootEl.classList.remove('open');
            opts.onSelect(group.id, null, group.name, null);
          });
          panel.appendChild(leafEl);
        });
        return;
      }
      groups.forEach(function (group) {
        const groupEl = document.createElement('div');
        groupEl.className = 'tree-select-group';
        const groupLabel = document.createElement('div');
        groupLabel.className = 'tree-select-group-label';
        groupLabel.innerHTML = '<span>' + group.name + '</span><span class="caret">&#9656;</span>';
        groupLabel.addEventListener('click', function (e) {
          e.stopPropagation();
          const wasExpanded = groupEl.classList.contains('expanded');
          panel.querySelectorAll('.tree-select-group').forEach(g => g.classList.remove('expanded'));
          if (!wasExpanded) groupEl.classList.add('expanded');
        });
        groupEl.appendChild(groupLabel);

        const childrenEl = document.createElement('div');
        childrenEl.className = 'tree-select-children';
        const children = opts.childrenFor(group.id);
        if (!children.length) {
          childrenEl.innerHTML = '<div class="tree-select-empty">' + (opts.emptyChildText || 'None yet.') + '</div>';
        }
        children.forEach(function (child) {
          const leafEl = document.createElement('div');
          leafEl.className = 'tree-select-leaf';
          leafEl.textContent = child.name;
          if (opts.selectedChildId !== undefined && String(opts.selectedChildId) === String(child.id) && String(opts.selectedGroupId) === String(group.id)) {
            leafEl.classList.add('selected');
            groupEl.classList.add('expanded');
            labelEl.textContent = group.name + ' › ' + child.name;
          }
          leafEl.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.querySelectorAll('.tree-select-leaf').forEach(l => l.classList.remove('selected'));
            leafEl.classList.add('selected');
            labelEl.textContent = group.name + ' › ' + child.name;
            rootEl.classList.remove('open');
            opts.onSelect(group.id, child.id, group.name, child.name);
          });
          childrenEl.appendChild(leafEl);
        });
        groupEl.appendChild(childrenEl);
        panel.appendChild(groupEl);
      });
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      document.querySelectorAll('.tree-select.open').forEach(el => { if (el !== rootEl) el.classList.remove('open'); });
      rootEl.classList.toggle('open');
    });
    panel.addEventListener('click', function (e) { e.stopPropagation(); });
    render();
  }

  document.addEventListener('click', function () {
    document.querySelectorAll('.tree-select.open').forEach(el => el.classList.remove('open'));
  });

  const categoryTree = document.getElementById('category_tree');
  if (categoryTree) {
    const categoryHidden = document.getElementById('category_select');
    const typeHidden = document.getElementById('asset_type_choice');
    initTreeSelect(categoryTree, categoryList, {
      childrenFor: (catId) => (categoryTypeMap[catId] || []).map(name => ({ id: name, name })),
      selectedGroupId: presetCategoryId,
      selectedChildId: presetAssetType,
      emptyText: 'No categories set up yet.',
      emptyChildText: 'No asset types in this category yet.',
      onSelect: (catId, typeName) => {
        categoryHidden.value = catId;
        typeHidden.value = typeName;
      },
    });
  }

  const categoryTreeOpex = document.getElementById('category_tree_opex');
  if (categoryTreeOpex) {
    const categoryHidden = document.getElementById('category_select');
    initTreeSelect(categoryTreeOpex, categoryList, {
      flat: true,
      selectedGroupId: presetCategoryId,
      emptyText: 'No categories set up yet.',
      onSelect: (catId) => { categoryHidden.value = catId; },
    });
  }

  const floorTree = document.getElementById('floor_tree');
  if (floorTree) {
    const floorHidden = document.getElementById('floor_select');
    const roomHidden = document.getElementById('room_select');
    initTreeSelect(floorTree, floorList, {
      childrenFor: (floorId) => roomsByFloor[floorId] || [],
      selectedGroupId: presetFloorId,
      selectedChildId: presetRoomId,
      emptyText: 'No floors set up yet.',
      emptyChildText: 'No rooms on this floor yet.',
      onSelect: (floorId, roomId) => {
        floorHidden.value = floorId;
        roomHidden.value = roomId;
      },
    });
  }

  const roomSelectEdit = document.getElementById('room_select_edit');
  if (roomSelectEdit) {
    const fixedFloorId = document.getElementById('floor_select').value;
    const options = roomsByFloor[fixedFloorId] || [];
    roomSelectEdit.innerHTML = options.length ? '<option value="">Select room</option>' : '<option value="">No rooms set up for this floor yet</option>';
    options.forEach(function (room) {
      const o = document.createElement('option');
      o.value = room.id; o.textContent = room.name;
      if (String(presetRoomId) === String(room.id)) o.selected = true;
      roomSelectEdit.appendChild(o);
    });
  }
})();
</script>

<style>
  .inventory-tree-select{position:relative}
  .inventory-tree-select .tree-select-trigger{width:100%;text-align:left;display:flex;justify-content:space-between;align-items:center;cursor:pointer}
  .inventory-tree-select .tree-select-trigger .chevron{opacity:.7;transition:transform .15s ease;margin-left:8px}
  .inventory-tree-select.open .tree-select-trigger{box-shadow:0 0 0 1px #ec3013;border-color:#ae1800}
  .inventory-tree-select.open .tree-select-trigger .chevron{transform:rotate(180deg)}
  .inventory-tree-select .tree-select-panel{display:none;position:absolute;top:calc(100% + 8px);left:0;right:0;background:#f3f2f2;border:1.5px solid #bab6b6;border-radius:0;box-shadow:var(--shadow-lg);z-index:50;max-height:320px;overflow-y:auto;padding:8px}
  .inventory-tree-select.open .tree-select-panel{display:block}
  .inventory-tree-select .tree-select-group{border-radius:0;margin-bottom:5px}
  .inventory-tree-select .tree-select-group-label{display:flex;align-items:center;justify-content:space-between;padding:11px 13px;cursor:pointer;font-size:12px;font-weight:800;color:#2d2b2b;border-radius:0;background:#f3f2f2}
  .inventory-tree-select .tree-select-group-label:hover{background:#eae9e9}
  .inventory-tree-select .tree-select-group.expanded > .tree-select-group-label{background:#ae1800;color:#f3f2f2}
  .inventory-tree-select .tree-select-group-label .caret{font-size:10px;opacity:.8;transition:transform .15s ease}
  .inventory-tree-select .tree-select-group.expanded > .tree-select-group-label .caret{transform:rotate(90deg)}
  .inventory-tree-select .tree-select-children{display:none;padding-left:14px;border-left:1px solid #d7d3d3;margin:6px 0 8px 12px}
  .inventory-tree-select .tree-select-group.expanded > .tree-select-children{display:block}
  .inventory-tree-select .tree-select-leaf{padding:10px 12px;font-size:11.5px;cursor:pointer;border-radius:0;color:#444141;font-weight:700}
  .inventory-tree-select .tree-select-leaf:hover{background:#f3f2f2;color:#2d2b2b}
  .inventory-tree-select .tree-select-leaf.selected{background:#eae9e9;color:#ae1800;font-weight:800}
  .inventory-tree-select .tree-select-empty{padding:10px 11px;font-size:11px;color:#7d7979}
</style>
