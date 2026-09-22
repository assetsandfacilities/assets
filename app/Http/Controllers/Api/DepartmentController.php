<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index() { return Department::all(); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:departments,code',
            'opex_limit' => 'required|numeric|min:0',
        ]);

        return Department::create($data);
    }

    public function show(string $id) { return Department::findOrFail($id); }

    public function update(Request $request, string $id)
    {
        $department = Department::findOrFail($id);
        $department->update($request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:departments,code,' . $department->id,
            'opex_limit' => 'required|numeric|min:0',
        ]));
        return $department;
    }

    public function destroy(string $id)
    {
        Department::findOrFail($id)->delete();
        return response()->json(['message' => 'Department deleted']);
    }
}
