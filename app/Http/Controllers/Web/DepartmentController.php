<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('users')->orderBy('name')->paginate(10);
        $departments->getCollection()->transform(function ($department) {
            $department->opex_consumed = $department->opexConsumed();
            $department->opex_remaining = $department->opexRemaining();
            return $department;
        });
        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        return view('departments.create');
    }

    public function store(Request $request)
    {
        // Only OPEX draws on a department budget, so opex_limit is the single
        // budget field. capex_limit and restrict_supply_requests are no longer
        // editable: the column stays in the table for backward compatibility,
        // but nothing in the app writes to them from this screen anymore.
        Department::create($request->validate([
            'name' => ['required','string','max:150'],
            'code' => ['required','string','max:50','unique:departments,code'],
            'opex_limit' => ['required','numeric','min:0'],
        ]));

        return redirect()->route('departments.index')->with('success', 'Department created successfully.');
    }

    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $department->update($request->validate([
            'name' => ['required','string','max:150'],
            'code' => ['required','string','max:50','unique:departments,code,'.$department->id],
            'opex_limit' => ['required','numeric','min:0'],
        ]));

        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return redirect()->route('departments.index')->with('success', 'Department deleted successfully.');
    }
}
