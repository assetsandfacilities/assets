<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use Illuminate\Http\Request;

class AllocationController extends Controller
{
    public function index() { return Allocation::with('department')->get(); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'item_type' => 'required|in:CAPEX,OPEX',
            'max_quantity' => 'required|integer|min:1',
            'period_label' => 'required|string|max:50',
        ]);
        return Allocation::create($data)->load('department');
    }

    public function show(string $id) { return Allocation::with('department')->findOrFail($id); }

    public function update(Request $request, string $id)
    {
        $allocation = Allocation::findOrFail($id);
        $allocation->update($request->validate([
            'department_id' => 'required|exists:departments,id',
            'item_type' => 'required|in:CAPEX,OPEX',
            'max_quantity' => 'required|integer|min:1',
            'period_label' => 'required|string|max:50',
        ]));
        return $allocation->load('department');
    }

    public function destroy(string $id) { Allocation::findOrFail($id)->delete(); return response()->json(['message' => 'Allocation deleted']); }
}
