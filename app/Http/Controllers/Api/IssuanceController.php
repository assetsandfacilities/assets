<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issuance;
use App\Models\Requisition;
use Illuminate\Http\Request;

class IssuanceController extends Controller
{
    public function index()
    {
        return Issuance::latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'requisition_id' => 'required|integer|exists:requisitions,id',
            'received_by' => 'required|integer|exists:users,id',
            'remarks' => 'nullable|string',
        ]);

        $requisition = Requisition::findOrFail($data['requisition_id']);
        if ($requisition->status !== 'approved') {
            return response()->json(['message' => 'Only approved requisitions can be issued.'], 422);
        }

        $issuance = Issuance::create([
            'requisition_id' => $requisition->id,
            'issued_by' => $request->user()->id,
            'received_by' => $data['received_by'],
            'issued_at' => now(),
            'remarks' => $data['remarks'] ?? null,
            'status' => 'issued',
        ]);

        $requisition->update(['status' => 'issued']);

        return response()->json($issuance);
    }

    public function returnItem(Request $request, string $id)
    {
        $issuance = Issuance::findOrFail($id);
        $issuance->update(['status' => 'returned']);
        return response()->json(['message' => 'Asset marked as returned.']);
    }
}
