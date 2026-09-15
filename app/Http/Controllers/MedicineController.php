<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Supplier;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException; // ← ADD THIS
use Illuminate\Support\Facades\Log; // ← ADD THIS

class MedicineController extends Controller
{
    public function index(Request $request)
    {
        $query = Medicine::with(['company', 'company.supplier']);

        // Filter by name - searches ALL records
        if ($request->filled('filter_name')) {
            $query->where('name', 'like', '%' . $request->filter_name . '%');
        }

        // Filter by product code - searches ALL records
        if ($request->filled('filter_product_code')) {
            $query->where('product_code', 'like', '%' . $request->filter_product_code . '%');
        }

        // Filter by unit - searches ALL records
        if ($request->filled('filter_unit')) {
            $query->where('unit', 'like', '%' . $request->filter_unit . '%');
        }

        // Filter by pack type - filters ALL records
        if ($request->filled('filter_pack_type')) {
            $query->where('pack_type', $request->filter_pack_type);
        }

        // Filter by stock level - filters ALL records
        if ($request->filled('filter_stock')) {
            switch ($request->filter_stock) {
                case 'low':
                    $query->where('current_stock', '<', 10);
                    break;
                case 'medium':
                    $query->whereBetween('current_stock', [10, 50]);
                    break;
                case 'high':
                    $query->where('current_stock', '>', 50);
                    break;
                case 'out':
                    $query->where('current_stock', 0);
                    break;
            }
        }

        // Apply pagination AFTER all filters
        $medicines = $query->paginate(20);

        return view('medicines.index', compact('medicines'));
    }

    public function create()
    {
        $companies = Company::with('supplier')->where('is_active', true)->get();
        return view('medicines.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'unit' => 'nullable|string|max:50',
            'cost' => 'nullable|numeric|min:0',
            'pack_type' => ['required', Rule::in(['pack', 'loose'])],
            'pack_size' => 'nullable|integer|min:1|required_if:pack_type,pack',
            'max_stock_limit' => 'nullable|integer|min:0',
            'current_stock' => 'nullable|integer|min:0',
        ]);

        // Check for duplicate medicine name within same company
        $exists = Medicine::where('name', $validated['name'])
            ->where('company_id', $validated['company_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'This medicine already exists for this company.')
                ->withInput();
        }

        Medicine::create($validated);

        return redirect()->route('medicines.index')
            ->with('success', 'Medicine created successfully.');
    }

    /**
     * Display the specified medicine.
     */
    public function show($id)
{
    try {
        Log::info('Fetching medicine details', ['id' => $id]);
        
        // Find medicine with relationships
        $medicine = Medicine::with([
            'company',
            'suppliers'  // Load the suppliers through the pivot table
        ])->find($id);
        
        if (!$medicine) {
            Log::warning('Medicine not found', ['id' => $id]);
            return response()->json([
                'error' => 'Medicine not found',
                'message' => 'No medicine found with ID: ' . $id
            ], 404);
        }
        
        Log::info('Medicine found', [
            'id' => $medicine->id,
            'name' => $medicine->name,
            'suppliers_count' => $medicine->suppliers->count()
        ]);
        
        // Get supplier data - directly from the suppliers relationship
        $supplierData = null;
        
        // Try to get primary supplier first
        $primarySupplier = $medicine->suppliers()
            ->wherePivot('is_primary', true)
            ->first();
        
        if ($primarySupplier) {
            // The supplier is already loaded, use it directly
            $supplierData = $primarySupplier;
        } elseif ($medicine->suppliers->isNotEmpty()) {
            // Fallback to first supplier
            $supplierData = $medicine->suppliers->first();
        }
        
        // Build response
        $response = [
            'id' => $medicine->id,
            'product_code' => $medicine->product_code,
            'name' => $medicine->name,
            'unit' => $medicine->unit,
            'pack_type' => $medicine->pack_type,
            'pack_size' => $medicine->pack_size,
            'cost' => $medicine->cost,
            'current_stock' => $medicine->current_stock,
            'max_stock_limit' => $medicine->max_stock_limit,
            'is_active' => $medicine->is_active,
            'category_name' => $medicine->category_name,
            'sub_category_name' => $medicine->sub_category_name,
            'generics' => $medicine->generics,
            'created_at' => $medicine->created_at ? $medicine->created_at->toISOString() : null,
            'updated_at' => $medicine->updated_at ? $medicine->updated_at->toISOString() : null,
            'company' => $medicine->company ? [
                'id' => $medicine->company->id,
                'name' => $medicine->company->name,
                'contact_person' => $medicine->company->contact_person,
                'email' => $medicine->company->email,
                'phone' => $medicine->company->phone,
                'address' => $medicine->company->address,
            ] : null,
            'supplier' => $supplierData ? [
                'id' => $supplierData->id,
                'name' => $supplierData->name,
                'contact_person' => $supplierData->contact_person,
                'email' => $supplierData->email,
                'phone' => $supplierData->phone,
                'address' => $supplierData->address,
            ] : null,
        ];
        
        return response()->json($response);
        
    } catch (\Exception $e) {
        Log::error('Error fetching medicine details', [
            'id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'Failed to load medicine details',
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function edit($id)
    {
        $medicine = Medicine::with(['company', 'suppliers'])->findOrFail($id);
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        
        // Get the primary supplier for this medicine
        $primarySupplier = $medicine->suppliers()
            ->where('is_primary', true)
            ->first();
        
        return view('medicines.edit', compact('medicine', 'companies', 'suppliers', 'primarySupplier'));
    }

    public function update(Request $request, Medicine $medicine)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'unit' => 'nullable|string|max:50',
            'cost' => 'nullable|numeric|min:0',
            'pack_type' => ['required', Rule::in(['pack', 'loose'])],
            'pack_size' => 'nullable|integer|min:1|required_if:pack_type,pack',
            'max_stock_limit' => 'nullable|integer|min:0',
            'current_stock' => 'nullable|integer|min:0',
        ]);

        // Check for duplicate medicine name within same company (excluding current)
        $exists = Medicine::where('name', $validated['name'])
            ->where('company_id', $validated['company_id'])
            ->where('id', '!=', $medicine->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'This medicine already exists for this company.')
                ->withInput();
        }

        $medicine->update($validated);

        return redirect()->route('medicines.index')
            ->with('success', 'Medicine updated successfully.');
    }

    public function destroy(Medicine $medicine)
    {
        try {
            $medicine->delete();
            return redirect()->route('medicines.index')
                ->with('success', 'Medicine deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Cannot delete medicine with existing order items.');
        }
    }

    public function updateStock(Request $request, Medicine $medicine)
    {
        $validated = $request->validate([
            'current_stock' => 'required|integer|min:0',
            'max_stock_limit' => 'nullable|integer|min:0',
        ]);

        $medicine->update($validated);

        return response()->json(['success' => true, 'message' => 'Stock updated successfully.']);
    }
}