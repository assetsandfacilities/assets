<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderBy('name')->paginate(10);
        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        Supplier::create($request->validate([
            'name' => ['required','string','max:150'],
            'contact_person' => ['nullable','string','max:150'],
            'email' => ['nullable','email','max:150'],
            'phone' => ['nullable','string','max:50'],
            'address' => ['nullable','string'],
        ]));
        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully.');
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($request->validate([
            'name' => ['required','string','max:150'],
            'contact_person' => ['nullable','string','max:150'],
            'email' => ['nullable','email','max:150'],
            'phone' => ['nullable','string','max:50'],
            'address' => ['nullable','string'],
        ]));
        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully.');
    }
}
