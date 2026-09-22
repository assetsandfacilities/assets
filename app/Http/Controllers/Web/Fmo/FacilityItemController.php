<?php

namespace App\Http\Controllers\Web\Fmo;

use App\Http\Controllers\Controller;
use App\Models\FacilityItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manages the two halves of the reservation form checklist:
 *   /fmo/items    -> "Other Items Needed"  (tables, chairs, speakers, ...)
 *   /fmo/services -> "Facility Services"   (ITSO, technical assistance, ...)
 *
 * Anything added here shows up on the Reservation Request / Activity Proposal
 * form right away; nothing on that form is hard-coded anymore.
 */
class FacilityItemController extends Controller
{
    private function typeFromRoute(Request $request): string
    {
        return str_contains($request->path(), 'services') ? FacilityItem::TYPE_SERVICE : FacilityItem::TYPE_ITEM;
    }

    public function index(Request $request): View
    {
        $type = $this->typeFromRoute($request);
        $search = trim((string) $request->string('search'));

        $records = FacilityItem::query()
            ->where('type', $type)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('fmo.catalog.index', [
            'records' => $records,
            'type' => $type,
            'search' => $search,
            'heading' => $type === FacilityItem::TYPE_SERVICE ? 'Facility Services' : 'Facility Items / Other Items Needed',
            'routeBase' => $type === FacilityItem::TYPE_SERVICE ? 'fmo.services' : 'fmo.items',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->typeFromRoute($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('facility_items', 'name')->where('type', $type)],
            'unit' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        FacilityItem::create($data + [
            'type' => $type,
            'allows_quantity' => $request->boolean('allows_quantity', true),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('success', ($type === FacilityItem::TYPE_SERVICE ? 'Service' : 'Item') . ' added and is now available on the Reservation form.');
    }

    public function update(Request $request, FacilityItem $facilityItem): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('facility_items', 'name')->where('type', $facilityItem->type)->ignore($facilityItem->id)],
            'unit' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $facilityItem->update($data + [
            'allows_quantity' => $request->boolean('allows_quantity'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Saved successfully.');
    }

    public function toggle(FacilityItem $facilityItem): RedirectResponse
    {
        $facilityItem->update(['is_active' => !$facilityItem->is_active]);

        return back()->with('success', '"' . $facilityItem->name . '" is now ' . ($facilityItem->is_active ? 'active and selectable' : 'inactive and hidden from the Reservation form') . '.');
    }

    /**
     * Deleting only removes the option from future forms. Reservations and
     * proposals already submitted keep their own saved copy of the item name
     * and quantity, so old records never lose data or break.
     */
    public function destroy(FacilityItem $facilityItem): RedirectResponse
    {
        $facilityItem->delete();

        return back()->with('success', 'Removed from the Reservation form. Existing reservation records keep their saved copy.');
    }
}
