<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Department;
use Illuminate\Http\Request;

class AllocationController extends Controller
{
    public function index()
    {
        $allocations = Allocation::with('department')->latest()->paginate(10);
        return view('allocations.index', compact('allocations'));
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get();
        return view('allocations.create', compact('departments'));
    }

    public function store(Request $request)
    {
        Allocation::create($request->validate([
            'department_id' => ['required','exists:departments,id'],
            'item_type' => ['required','in:CAPEX,OPEX'],
            'max_quantity' => ['required','integer','min:1'],
            'period_label' => ['required','string','max:50'],
        ]));
        return redirect()->route('allocations.index')->with('success', 'Allocation created successfully.');
    }

    public function edit(Allocation $allocation)
    {
        $departments = Department::orderBy('name')->get();
        return view('allocations.edit', compact('allocation','departments'));
    }

    public function update(Request $request, Allocation $allocation)
    {
        $allocation->update($request->validate([
            'department_id' => ['required','exists:departments,id'],
            'item_type' => ['required','in:CAPEX,OPEX'],
            'max_quantity' => ['required','integer','min:1'],
            'period_label' => ['required','string','max:50'],
        ]));
        return redirect()->route('allocations.index')->with('success', 'Allocation updated successfully.');
    }

    public function destroy(Allocation $allocation)
    {
        $allocation->delete();
        return redirect()->route('allocations.index')->with('success', 'Allocation deleted successfully.');
    }
}
