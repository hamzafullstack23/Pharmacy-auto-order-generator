<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with(['supplier', 'medicines'])
                           ->paginate(20);
        return view('companies.index', compact('companies'));
    }

    public function create()
    {
        $suppliers = Supplier::where('is_active', true)->get();
        return view('companies.create', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        Company::create($validated);

        return redirect()->route('companies.index')
                         ->with('success', 'Company created successfully.');
    }

    public function edit(Company $company)
    {
        $suppliers = Supplier::where('is_active', true)->get();
        return view('companies.edit', compact('company', 'suppliers'));
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('companies')->ignore($company->id)],
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'supplier_id' => 'required|exists:suppliers,id',
            'is_active' => 'boolean',
        ]);

        $company->update($validated);

        return redirect()->route('companies.index')
                         ->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company)
    {
        try {
            $company->delete();
            return redirect()->route('companies.index')
                             ->with('success', 'Company deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Cannot delete company with existing medicines.');
        }
    }

    public function show(Company $company)
    {
        $company->load(['supplier', 'medicines', 'orders']);
        return view('companies.show', compact('company'));
    }
}