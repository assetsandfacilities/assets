<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index()
    {
        return Item::with(['category'])->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'nullable|integer',
            'item_code' => 'required|string|max:100|unique:items,item_code',
            'name' => 'required|string|max:200',
            'item_type' => 'required|in:CAPEX,OPEX',
            'description' => 'nullable|string',
            'acquisition_date' => 'nullable|date',
            'assigned_department' => 'nullable|string|max:150',
            'asset_type_name' => 'nullable|string|max:150',
            'room_assigned' => 'nullable|string|max:100',
            'floor' => 'nullable|string|max:20',
            'quantity' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'low_stock_threshold' => 'required|integer|min:0',
        ]);

        if ($data['item_type'] === 'CAPEX') {
            $data['qr_value'] = $data['item_code'];
        }

        return Item::create($data);
    }

    public function show(string $id)
    {
        return Item::with('category')->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $item = Item::findOrFail($id);
        $data = $request->validate([
            'category_id' => 'nullable|integer',
            'item_code' => 'required|string|max:100|unique:items,item_code,' . $item->id,
            'name' => 'required|string|max:200',
            'item_type' => 'required|in:CAPEX,OPEX',
            'description' => 'nullable|string',
            'acquisition_date' => 'nullable|date',
            'assigned_department' => 'nullable|string|max:150',
            'asset_type_name' => 'nullable|string|max:150',
            'room_assigned' => 'nullable|string|max:100',
            'floor' => 'nullable|string|max:20',
            'quantity' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'low_stock_threshold' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        $data['qr_value'] = $data['item_type'] === 'CAPEX' ? $data['item_code'] : null;
        $item->update($data);
        return $item;
    }

    public function destroy(string $id)
    {
        Item::findOrFail($id)->delete();
        return response()->json(['message' => 'Item deleted']);
    }

    public function lookupByCode(string $code): JsonResponse
    {
        $normalizedCode = trim(urldecode($code));

        $item = Item::with('category')
            ->where('item_type', 'CAPEX')
            ->where(function ($query) use ($normalizedCode) {
                $query->where('item_code', $normalizedCode)
                    ->orWhere('qr_value', $normalizedCode);
            })
            ->firstOrFail();

        return response()->json([
            'asset_tag_id' => $item->asset_tag_id,
            'description' => $item->asset_description,
            'date_acquired' => $item->date_acquired_label,
            'department' => $item->department_label,
            'asset_type' => $item->asset_type_label,
            'room_assigned' => $item->room_assigned,
            'qr_value' => $item->qr_value ?: $item->item_code,
            'item' => $item,
        ]);
    }

}
