<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Item;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequisitionController extends Controller
{
    public function index()
    {
        return Requisition::with(['user', 'department', 'items.item'])->latest()->get();
    }

    public function show(string $id)
    {
        return Requisition::with(['user', 'department', 'items.item'])->findOrFail($id);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'purpose' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,id',
            'items.*.quantity_requested' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request, $data) {
            $department = \App\Models\Department::whereKey($data['department_id'])->lockForUpdate()->firstOrFail();

            $estimatedTotal = 0.0;
            foreach ($data['items'] as $entry) {
                $item = Item::findOrFail($entry['item_id']);
                $estimatedTotal += (float) $item->unit_price * (int) $entry['quantity_requested'];
            }
            $remaining = $department->opexRemaining();
            if ($estimatedTotal > $remaining) {
                abort(422, "Not enough OPEX budget in {$department->name}. Remaining: {$remaining}, requested: {$estimatedTotal}.");
            }

            $requisition = Requisition::create([
                'requisition_no' => 'REQ-' . now()->format('YmdHis'),
                'user_id' => $request->user()->id,
                'department_id' => $data['department_id'],
                'purpose' => $data['purpose'] ?? null,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            foreach ($data['items'] as $entry) {
                $item = Item::findOrFail($entry['item_id']);
                $allocation = Allocation::where('department_id', $data['department_id'])
                    ->where('item_type', $item->item_type)
                    ->first();

                if ($allocation && $entry['quantity_requested'] > $allocation->max_quantity) {
                    abort(422, "Requested quantity exceeds {$item->item_type} allocation limit.");
                }

                if ($entry['quantity_requested'] > $item->quantity) {
                    abort(422, "Requested quantity exceeds available stock for {$item->name}.");
                }

                RequisitionItem::create([
                    'requisition_id' => $requisition->id,
                    'item_id' => $item->id,
                    'quantity_requested' => $entry['quantity_requested'],
                    'quantity_approved' => null,
                    'unit_price' => $item->unit_price,
                    'total_amount' => round((float) $item->unit_price * (int) $entry['quantity_requested'], 2),
                ]);
            }

            return $requisition->load('items.item');
        });
    }

    public function approve(Request $request, string $id)
    {
        $requisition = Requisition::with('items.item')->findOrFail($id);

        DB::transaction(function () use ($request, $requisition) {
            foreach ($requisition->items as $reqItem) {
                $item = $reqItem->item;
                $approvedQty = $reqItem->quantity_requested;

                if ($approvedQty > $item->quantity) {
                    abort(422, "Insufficient stock for {$item->name}");
                }

                $item->decrement('quantity', $approvedQty);
                $reqItem->update(['quantity_approved' => $approvedQty]);
            }

            $requisition->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Requisition approved successfully.',
            'requisition' => $requisition->fresh(['items.item'])
        ]);
    }

    public function reject(Request $request, string $id)
    {
        $requisition = Requisition::findOrFail($id);
        $request->validate(['reason' => 'required|string']);

        $requisition->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $request->reason,
        ]);

        return response()->json(['message' => 'Requisition rejected successfully.']);
    }
}
