@php
    $treeId = $treeId ?? ('approver_tree_' . \Illuminate\Support\Str::slug($inputName, '_'));
    $requestedSelectedId = (string) old($inputName, $selectedId ?? '');
    $peopleCollection = collect($people ?? []);
    $fixedGroupName = $fixedGroupName ?? null;
    $groupedPeople = $fixedGroupName
        ? collect([$fixedGroupName => $peopleCollection->sortBy('name')])
        : $peopleCollection
            ->groupBy(fn($person) => $person->department->name ?? 'Other / Unassigned')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
    $selectedPerson = $peopleCollection->first(fn($person) => (string) $person->id === $requestedSelectedId);
    $selectedId = $selectedPerson ? $requestedSelectedId : '';
    $selectedDepartment = $selectedPerson
        ? ($fixedGroupName ?: ($selectedPerson?->department?->name ?? 'Other / Unassigned'))
        : null;
@endphp

<div class="approver-tree-select @error($inputName) invalid @enderror" id="{{ $treeId }}" data-required="{{ ($required ?? true) ? '1' : '0' }}">
    <button type="button" class="approver-tree-trigger form-select" aria-expanded="false">
        <span class="approver-tree-label">
            @if($selectedPerson)
                {{ $selectedDepartment }} › {{ $selectedPerson->name }}
            @else
                {{ $placeholder ?? 'Select department and approver' }}
            @endif
        </span>
        <span class="chevron">&#9662;</span>
    </button>

    <div class="approver-tree-panel" role="listbox">
        @forelse($groupedPeople as $departmentName => $departmentPeople)
            @php
                $departmentHasSelected = $departmentPeople->contains(fn($person) => (string) $person->id === $selectedId);
            @endphp
            <div class="approver-tree-group {{ $departmentHasSelected ? 'expanded' : '' }}">
                <button type="button" class="approver-tree-group-label">
                    <span><i class="bi bi-building me-2"></i>{{ $departmentName }}</span>
                    <span class="caret">&#9656;</span>
                </button>
                <div class="approver-tree-children">
                    @foreach($departmentPeople as $person)
                        <button
                            type="button"
                            class="approver-tree-leaf {{ (string) $person->id === $selectedId ? 'selected' : '' }}"
                            data-value="{{ $person->id }}"
                            data-person-name="{{ $person->name }}"
                            data-department-name="{{ $departmentName }}"
                        >
                            <i class="bi bi-person-check me-2"></i>{{ $person->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="approver-tree-empty">No eligible approvers are available for this role.</div>
        @endforelse
    </div>

    <input type="hidden" name="{{ $inputName }}" value="{{ $selectedId }}" class="approver-tree-value">
    <div class="invalid-feedback approver-tree-error">@error($inputName){{ $message }}@else Please select a department, then choose an approver. @enderror</div>
</div>

@once
    @push('styles')
    <style>
        .approver-tree-select{position:relative}
        .approver-tree-trigger{width:100%;text-align:left;display:flex;justify-content:space-between;align-items:center;cursor:pointer;background-color:var(--surface-2)}
        .approver-tree-trigger .chevron{opacity:.6;transition:transform .15s ease;margin-left:8px}
        .approver-tree-select.open .approver-tree-trigger{box-shadow:0 0 0 1px #ec3013;border-color:var(--gold-500)}
        .approver-tree-select.open .approver-tree-trigger .chevron{transform:rotate(180deg)}
        .approver-tree-select.invalid .approver-tree-trigger{border-color:#ec3013;box-shadow:none}
        .approver-tree-panel{display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;background:var(--surface-2);border:1px solid var(--line);border-radius:0;box-shadow:var(--shadow-lg);z-index:80;max-height:340px;overflow-y:auto;padding:6px}
        .approver-tree-select.open .approver-tree-panel{display:block}
        .approver-tree-group + .approver-tree-group{margin-top:3px}
        .approver-tree-group-label{width:100%;border:0;background:transparent;display:flex;align-items:center;justify-content:space-between;padding:10px;cursor:pointer;font-size:13px;font-weight:700;color:var(--ink-900);border-radius:0;text-align:left}
        .approver-tree-group-label:hover{background:var(--surface)}
        .approver-tree-group.expanded > .approver-tree-group-label{background:var(--navy-800);color:#f3f2f2}
        .approver-tree-group-label .caret{font-size:10px;opacity:.65;transition:transform .15s ease}
        .approver-tree-group.expanded > .approver-tree-group-label .caret{transform:rotate(90deg)}
        .approver-tree-children{display:none;padding:4px 0 4px 12px}
        .approver-tree-group.expanded > .approver-tree-children{display:block}
        .approver-tree-leaf{width:100%;border:0;background:transparent;text-align:left;padding:9px 10px;border-radius:0;font-size:13px;color:var(--ink-800);cursor:pointer}
        .approver-tree-leaf:hover{background:var(--surface)}
        .approver-tree-leaf.selected{background:rgba(236,48,19,.16);font-weight:700;color:var(--ink-900)}
        .approver-tree-empty{padding:12px;color:var(--muted);font-size:12.5px}
        .approver-tree-error{display:none}
        .approver-tree-select.invalid .approver-tree-error{display:block}
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        function closeOtherTrees(except) {
            document.querySelectorAll('.approver-tree-select.open').forEach(function (tree) {
                if (tree !== except) {
                    tree.classList.remove('open');
                    tree.querySelector('.approver-tree-trigger')?.setAttribute('aria-expanded', 'false');
                }
            });
        }

        document.querySelectorAll('.approver-tree-select').forEach(function (tree) {
            if (tree.dataset.initialized === '1') return;
            tree.dataset.initialized = '1';

            const trigger = tree.querySelector('.approver-tree-trigger');
            const label = tree.querySelector('.approver-tree-label');
            const valueInput = tree.querySelector('.approver-tree-value');
            const panel = tree.querySelector('.approver-tree-panel');

            trigger?.addEventListener('click', function (event) {
                event.stopPropagation();
                const willOpen = !tree.classList.contains('open');
                closeOtherTrees(tree);
                tree.classList.toggle('open', willOpen);
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });

            tree.querySelectorAll('.approver-tree-group-label').forEach(function (groupButton) {
                groupButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    const group = groupButton.closest('.approver-tree-group');
                    const wasExpanded = group.classList.contains('expanded');
                    tree.querySelectorAll('.approver-tree-group').forEach(function (item) { item.classList.remove('expanded'); });
                    if (!wasExpanded) group.classList.add('expanded');
                });
            });

            tree.querySelectorAll('.approver-tree-leaf').forEach(function (leaf) {
                leaf.addEventListener('click', function (event) {
                    event.stopPropagation();
                    tree.querySelectorAll('.approver-tree-leaf').forEach(function (item) { item.classList.remove('selected'); });
                    leaf.classList.add('selected');
                    valueInput.value = leaf.dataset.value || '';
                    label.textContent = (leaf.dataset.departmentName || '') + ' › ' + (leaf.dataset.personName || '');
                    tree.classList.remove('invalid', 'open');
                    trigger.setAttribute('aria-expanded', 'false');
                    valueInput.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            panel?.addEventListener('click', function (event) { event.stopPropagation(); });
        });

        document.addEventListener('click', function () { closeOtherTrees(null); });

        document.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.approverTreeValidation === '1') return;
            form.dataset.approverTreeValidation = '1';
            form.addEventListener('submit', function (event) {
                let firstInvalid = null;
                form.querySelectorAll('.approver-tree-select[data-required="1"]').forEach(function (tree) {
                    const value = tree.querySelector('.approver-tree-value')?.value || '';
                    const invalid = value === '';
                    tree.classList.toggle('invalid', invalid);
                    if (invalid && !firstInvalid) firstInvalid = tree;
                });
                if (firstInvalid) {
                    event.preventDefault();
                    closeOtherTrees(firstInvalid);
                    firstInvalid.classList.add('open');
                    firstInvalid.querySelector('.approver-tree-trigger')?.setAttribute('aria-expanded', 'true');
                    firstInvalid.querySelector('.approver-tree-trigger')?.focus();
                }
            });
        });
    })();
    </script>
    @endpush
@endonce
