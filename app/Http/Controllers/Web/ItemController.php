<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\Department;
use App\Models\Floor;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    /**
     * Asset types grouped by category name, e.g. ['Electronics' => ['Laptop', 'Monitor', ...]].
     * Backed by the asset_types table, managed by Super Admin only (see ReferenceDataController).
     */
    private function assetTypeOptionsArray(): array
    {
        return AssetType::with('category')->get()
            ->filter(fn ($t) => $t->category !== null)
            ->groupBy(fn ($t) => $t->category->name)
            ->map(fn ($group) => $group->pluck('name')->sort()->values()->all())
            ->all();
    }

    /**
     * Rooms grouped by floor_id, e.g. [4 => ['719', '718', ...]] used to drive the
     * Floor -> Room cascading dropdown on the CAPEX form, same pattern as Category -> Asset Type.
     */
    private function roomOptionsByFloor(): array
    {
        return Room::orderBy('name')->get()
            ->groupBy('floor_id')
            ->map(fn ($group) => $group->map(fn ($r) => ['id' => $r->id, 'name' => $r->label()])->values()->all())
            ->all();
    }

    public function index(Request $request)
    {
        $type = strtoupper((string) $request->get('type', 'CAPEX'));
        if (!in_array($type, ['CAPEX', 'OPEX'], true)) {
            $type = 'CAPEX';
        }
        $user = auth()->user();
        if ($user?->isRequestor()) {
            $type = 'OPEX';
        }

        $search = trim((string) $request->get('search'));
        $stockFilter = $request->get('stock_filter');
        $floorFilter = $request->get('floor_id');
        $roomFilter = $request->get('room_id');

        $items = Item::with(['category', 'floorRef', 'room'])
            ->where('item_type', $type)
            ->when($user?->isRequestor(), function ($query) use ($type) {
                if ($type === 'OPEX') {
                    $query->where('is_active', true)
                        ->where('availability_status', '!=', 'Out of Stock')
                        ->where('quantity', '>', 0);
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('qr_value', 'like', "%{$search}%")
                        ->orWhere('room_assigned', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('specifications', 'like', "%{$search}%");
                });
            })
            ->when($stockFilter === 'low', fn ($q) => $q->where('availability_status', 'Limited Stock'))
            ->when($stockFilter === 'available', fn ($q) => $q->where('availability_status', 'Available'))
            ->when($stockFilter === 'out', fn ($q) => $q->where('availability_status', 'Out of Stock'))
            ->when($stockFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($floorFilter, fn ($q) => $q->where('floor_id', $floorFilter))
            ->when($roomFilter, fn ($q) => $q->where('room_id', $roomFilter))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $floors = Floor::orderBy('sort_order')->orderBy('name')->get();
        $rooms = $floorFilter ? Room::where('floor_id', $floorFilter)->orderBy('name')->get() : collect();

        return view('items.index', compact('items', 'type', 'search', 'stockFilter', 'floors', 'rooms', 'floorFilter', 'roomFilter'));
    }

    public function create(Request $request)
    {
        abort_unless(auth()->user()->canManageInventory(), 403);
        $type = strtoupper((string) $request->get('type', 'CAPEX'));
        if (!in_array($type, ['CAPEX', 'OPEX'], true)) {
            $type = 'CAPEX';
        }
        $categories = ItemCategory::where(function ($q) use ($type) {
            $q->where('item_type', $type)->orWhere('item_type', 'BOTH');
        })->orderBy('name')->get();
        $suggestedCode = $type === 'OPEX' ? $this->generateItemCode($type) : null;
        $floors = Floor::orderBy('sort_order')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $assetTypeOptions = $this->assetTypeOptionsArray();
        $roomOptions = $this->roomOptionsByFloor();
        return view('items.create', compact('categories', 'type', 'suggestedCode', 'floors', 'assetTypeOptions', 'departments', 'roomOptions'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->canManageInventory(), 403);
        $data = $request->validate([
            'category_id' => ['required', 'exists:item_categories,id'],
            'item_code' => ['nullable','string','max:100','unique:items,item_code'],
            'name' => ['required','string','max:200'],
            'item_type' => ['required','in:CAPEX,OPEX'],
            'description' => ['nullable','string'],
            'specifications' => ['nullable','string'],
            'acquisition_date' => ['nullable','date'],
            'assigned_department_id' => ['nullable','exists:departments,id'],
            'asset_type_name' => ['nullable','string','max:150'],
            'quantity' => ['nullable','integer','min:0'],
            'unit' => ['nullable','string','max:50'],
            'unit_price' => ['nullable','numeric','min:0'],
            'brand' => ['nullable','string','max:100'],
            'low_stock_threshold' => ['nullable','integer','min:0'],
            'availability_status' => ['nullable', 'in:Available,Limited Stock,Out of Stock'],
            'floor_id' => ['required_if:item_type,CAPEX','nullable','exists:floors,id'],
            'room_id' => ['required_if:item_type,CAPEX','nullable','exists:rooms,id'],
            'unit_count' => ['nullable', 'integer', 'min:1', 'max:300'],
            'image_file' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
            'is_active' => ['nullable','boolean'],
        ]);

        $data = $this->resolveMasterDataMirrors($data);

        if ($request->hasFile('image_file')) {
            $data['image_path'] = $this->storeUploadedImage($request->file('image_file'));
        }
        unset($data['image_file']);

        $unitCount = $data['item_type'] === 'CAPEX' ? max(1, (int) ($data['unit_count'] ?? 1)) : 1;
        unset($data['unit_count']);

        $data['is_active'] = $request->boolean('is_active', true);

        if ($data['item_type'] === 'CAPEX') {
            $data['quantity'] = 1;
            $data['unit'] = ($data['unit'] ?? '') ?: 'unit';
            $data['unit_price'] = $data['unit_price'] ?? 0;
            $data['low_stock_threshold'] = 0;
            $data['availability_status'] = 'Available';

            $baseName = $data['name'];
            for ($i = 0; $i < $unitCount; $i++) {
                $unitData = $data;
                // Every unit gets its own randomly-generated, collision-checked asset tag,
                // scoped to the same floor the admin selected -- no manual typing needed
                // even when adding dozens of the same item at once.
                $unitData['item_code'] = $this->generateItemCode('CAPEX', $data['floor'] ?? null);
                $unitData['qr_value'] = $unitData['item_code'];
                $unitData['name'] = $unitCount > 1 ? $baseName . ' (Unit ' . ($i + 1) . ' of ' . $unitCount . ')' : ($baseName ?: $unitData['item_code']);
                Item::create($unitData);
            }

            $message = $unitCount > 1
                ? "{$unitCount} units created successfully, each with its own asset tag ID."
                : 'Item created successfully.';

            return redirect()->route('items.index', ['type' => 'CAPEX', 'floor_id' => $data['floor_id'] ?? null, 'room_id' => $data['room_id'] ?? null])->with('success', $message);
        }

        if (empty($data['item_code'])) {
            $data['item_code'] = $this->generateItemCode($data['item_type']);
        }
        $data['quantity'] = $data['quantity'] ?? 0;
        $data['unit'] = ($data['unit'] ?? '') ?: 'pcs';
        $data['unit_price'] = $data['unit_price'] ?? 0;
        $data['low_stock_threshold'] = $data['low_stock_threshold'] ?? 0;
        $data['availability_status'] = $data['availability_status'] ?? 'Available';
        $data['qr_value'] = null;
        if ($data['availability_status'] === 'Out of Stock') {
            $data['quantity'] = 0;
        }
        $data['room_assigned'] = null;
        $data['room_id'] = null;

        Item::create($data);
        return redirect()->route('items.index', ['type' => $data['item_type']])->with('success', 'Item created successfully.');
    }

    public function edit(Item $item)
    {
        abort_unless(auth()->user()->canManageInventory(), 403);
        $type = $item->item_type;
        $categories = ItemCategory::where(function ($q) use ($type, $item) {
            $q->where('item_type', $type)->orWhere('item_type', 'BOTH')->orWhere('id', $item->category_id);
        })->orderBy('name')->get();
        $floors = Floor::orderBy('sort_order')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $assetTypeOptions = $this->assetTypeOptionsArray();
        $roomOptions = $this->roomOptionsByFloor();
        return view('items.edit', compact('item','categories', 'type', 'floors', 'assetTypeOptions', 'departments', 'roomOptions'));
    }

    public function update(Request $request, Item $item)
    {
        abort_unless(auth()->user()->canManageInventory(), 403);
        $data = $request->validate([
            'category_id' => ['required', 'exists:item_categories,id'],
            'item_code' => ['nullable','string','max:100','unique:items,item_code,'.$item->id],
            'name' => ['required','string','max:200'],
            'item_type' => ['required','in:CAPEX,OPEX'],
            'description' => ['nullable','string'],
            'specifications' => ['nullable','string'],
            'acquisition_date' => ['nullable','date'],
            'assigned_department_id' => ['nullable','exists:departments,id'],
            'asset_type_name' => ['nullable','string','max:150'],
            'quantity' => ['nullable','integer','min:0'],
            'unit' => ['nullable','string','max:50'],
            'unit_price' => ['nullable','numeric','min:0'],
            'brand' => ['nullable','string','max:100'],
            'low_stock_threshold' => ['nullable','integer','min:0'],
            'availability_status' => ['nullable', 'in:Available,Limited Stock,Out of Stock'],
            'floor_id' => ['required_if:item_type,CAPEX','nullable','exists:floors,id'],
            'room_id' => ['required_if:item_type,CAPEX','nullable','exists:rooms,id'],
            'image_file' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
            'remove_image' => ['nullable','boolean'],
            'is_active' => ['nullable','boolean'],
        ]);

        $data = $this->resolveMasterDataMirrors($data);

        if ($request->boolean('remove_image')) {
            $this->deleteUploadedImage($item->image_path);
            $data['image_path'] = null;
        }

        if ($request->hasFile('image_file')) {
            $this->deleteUploadedImage($item->image_path);
            $data['image_path'] = $this->storeUploadedImage($request->file('image_file'));
        }
        unset($data['image_file'], $data['remove_image']);

        $data['is_active'] = $request->boolean('is_active', false);
        if (empty($data['item_code'])) {
            $data['item_code'] = $this->generateItemCode($data['item_type']);
        }

        if ($data['item_type'] === 'CAPEX') {
            $data['quantity'] = 1;
            $data['name'] = $data['name'] ?: $data['item_code'];
            $data['unit'] = ($data['unit'] ?? '') ?: 'unit';
            $data['unit_price'] = $data['unit_price'] ?? 0;
            $data['low_stock_threshold'] = 0;
            $data['availability_status'] = 'Available';
        } else {
            $data['quantity'] = $data['quantity'] ?? 0;
            $data['unit'] = ($data['unit'] ?? '') ?: 'pcs';
            $data['unit_price'] = $data['unit_price'] ?? 0;
            $data['low_stock_threshold'] = $data['low_stock_threshold'] ?? 0;
            $data['availability_status'] = $data['availability_status'] ?? 'Available';
        }
        $data['qr_value'] = $data['item_type'] === 'CAPEX' ? $data['item_code'] : null;
        if ($data['availability_status'] === 'Out of Stock') {
            $data['quantity'] = 0;
        }
        if ($data['item_type'] === 'OPEX') {
            $data['room_assigned'] = null;
            $data['room_id'] = null;
        }
        $item->update($data);
        return redirect()->route('items.index', ['type' => $data['item_type']])->with('success', 'Item updated successfully.');
    }

    public function show(Item $item)
    {
        if (auth()->user()?->isRequestor() && $item->item_type === 'OPEX' && $item->isOutOfStock()) {
            abort(404);
        }
        $item->load(['category', 'floorRef', 'room', 'assignedDepartment']);
        return view('items.show', compact('item'));
    }

    public function destroy(Item $item)
    {
        abort_unless(auth()->user()->canManageInventory(), 403);
        $type = $item->item_type;
        $this->deleteUploadedImage($item->image_path);
        $item->delete();
        return redirect()->route('items.index', ['type' => $type])->with('success', 'Item deleted successfully.');
    }

    /**
     * Floor/Room/Department are now real dropdown-driven master data (floor_id/room_id/
     * assigned_department_id), but a lot of existing code across this app -- asset tag
     * generation, mismatch detection, reports, the mobile API response -- still reads the
     * older plain-text columns (floor, room_assigned, assigned_department). Rather than
     * rewrite all of that, this keeps the text columns as an auto-synced mirror of whatever
     * was picked in the dropdown, so nothing downstream breaks.
     */
    private function resolveMasterDataMirrors(array $data): array
    {
        if (!empty($data['floor_id'])) {
            $data['floor'] = Floor::find($data['floor_id'])?->name;
        }
        if (!empty($data['room_id'])) {
            $room = Room::find($data['room_id']);
            $data['room_assigned'] = $room?->name;
        }
        if (!empty($data['assigned_department_id'])) {
            $data['assigned_department'] = Department::find($data['assigned_department_id'])?->name;
        }
        return $data;
    }

    private function generateItemCode(string $type, ?string $floor = null): string
    {
        if (strtoupper($type) === 'CAPEX') {
            $floorDigit = preg_replace('/\D/', '', $floor ?: (Floor::orderBy('sort_order')->orderBy('name')->value('name') ?: '4th Floor'));
            $floorDigit = $floorDigit !== '' ? $floorDigit : '4';
            do {
                $candidate = $floorDigit . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (Item::where('item_code', $candidate)->exists());
            return $candidate;
        }

        $prefix = 'OPEX-AMO-';
        $latest = Item::where('item_code', 'like', $prefix.'%')->pluck('item_code');
        $max = 0;
        foreach ($latest as $code) {
            $suffix = str_replace($prefix, '', $code);
            $number = (int) preg_replace('/\D/', '', $suffix);
            if ($number > $max) {
                $max = $number;
            }
        }
        $next = $max + 1;
        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function storeUploadedImage($file): string
    {
        $directory = public_path('uploads/items');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return 'uploads/items/'.$filename;
    }

    private function deleteUploadedImage(?string $imagePath): void
    {
        if (!$imagePath || str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://') || str_starts_with($imagePath, 'data:image/')) {
            return;
        }

        $fullPath = public_path($imagePath);
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
}
