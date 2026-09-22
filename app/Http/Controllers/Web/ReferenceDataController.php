<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\Floor;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Room;
use Illuminate\Http\Request;

class ReferenceDataController extends Controller
{
    public function index()
    {
        $floors = Floor::withCount('rooms', 'items')->orderBy('sort_order')->orderBy('name')->get();
        $rooms = Room::with('floor')->withCount('items')->orderBy('floor_id')->orderBy('name')->get();
        $categories = ItemCategory::withCount('items', 'assetTypes')->orderBy('name')->get();
        $assetTypes = AssetType::with('category')->orderBy('item_category_id')->orderBy('name')->get();

        return view('reference-data.index', compact('floors', 'rooms', 'categories', 'assetTypes'));
    }

    // ----- Floors -----
    public function storeFloor(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:floors,name'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        Floor::create(['name' => $data['name'], 'sort_order' => $data['sort_order'] ?? 0]);
        return back()->with('success', 'Floor added.');
    }

    public function destroyFloor(Floor $floor)
    {
        if ($floor->items()->exists() || $floor->rooms()->exists()) {
            return back()->withErrors(['floor' => 'Cannot delete "' . $floor->name . '" — it still has rooms or assets assigned to it.']);
        }
        $floor->delete();
        return back()->with('success', 'Floor removed.');
    }

    // ----- Rooms -----
    public function storeRoom(Request $request)
    {
        $data = $request->validate([
            'floor_id' => ['required', 'exists:floors,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
        ]);
        Room::create($data);
        return back()->with('success', 'Room added.');
    }

    public function destroyRoom(Room $room)
    {
        if ($room->items()->exists()) {
            return back()->withErrors(['room' => 'Cannot delete "' . $room->name . '" — it still has assets assigned to it.']);
        }
        $room->delete();
        return back()->with('success', 'Room removed.');
    }

    // ----- Categories -----
    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:item_categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'item_type' => ['required', 'in:CAPEX,OPEX,BOTH'],
        ]);
        ItemCategory::create($data);
        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, ItemCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:item_categories,name,'.$category->id],
            'description' => ['nullable', 'string', 'max:255'],
            'item_type' => ['required', 'in:CAPEX,OPEX,BOTH'],
        ]);
        $category->update($data);
        return back()->with('success', 'Category "'.$category->name.'" updated.');
    }

    public function destroyCategory(ItemCategory $category)
    {
        if ($category->items()->exists()) {
            return back()->withErrors(['category' => 'Cannot delete "' . $category->name . '" — it still has items assigned to it.']);
        }
        $category->assetTypes()->delete();
        $category->delete();
        return back()->with('success', 'Category removed.');
    }

    // ----- Asset Types -----
    public function storeAssetType(Request $request)
    {
        $data = $request->validate([
            'item_category_id' => ['required', 'exists:item_categories,id'],
            'name' => ['required', 'string', 'max:150'],
        ]);
        AssetType::firstOrCreate($data);
        return back()->with('success', 'Asset type added.');
    }

    public function destroyAssetType(AssetType $assetType)
    {
        $inUse = Item::where('category_id', $assetType->item_category_id)->where('asset_type_name', $assetType->name)->exists();
        if ($inUse) {
            return back()->withErrors(['asset_type' => 'Cannot delete "' . $assetType->name . '" — it is still used by existing items.']);
        }
        $assetType->delete();
        return back()->with('success', 'Asset type removed.');
    }
}
