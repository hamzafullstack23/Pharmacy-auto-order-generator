<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Imports\SuppliersImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount(['orders', 'medicines'])->paginate(20);
        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        // Check if this is a bulk upload
        if ($request->hasFile('bulk_file')) {
            return $this->handleBulkUpload($request);
        }

        // Regular single supplier creation
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'order_day' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
        ]);

        $supplier = Supplier::create($validated);

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' created successfully.");
    }

    // protected function handleBulkUpload(Request $request)
    // {
    //     $request->validate([
    //         'bulk_file' => [
    //             'required',
    //             'file',
    //             'max:102400',
    //             function ($attribute, $value, $fail) {
    //                 $extension = strtolower($value->getClientOriginalExtension());
    //                 $allowed = ['csv', 'xlsx', 'xls', 'xlsm', 'xlsb'];

    //                 if (!in_array($extension, $allowed)) {
    //                     $fail("The file must be a valid Excel or CSV file (allowed: .csv, .xlsx, .xls).");
    //                 }
    //             },
    //         ],
    //     ]);

    //     try {
    //         $import = new SuppliersImport();
    //         $file = $request->file('bulk_file');

    //         // ===== FIX: Specify the reader explicitly =====
    //         $extension = strtolower($file->getClientOriginalExtension());

    //         if ($extension === 'csv') {
    //             Excel::import($import, $file, null, \Maatwebsite\Excel\Excel::CSV);
    //         } elseif ($extension === 'xls') {
    //             Excel::import($import, $file, null, \Maatwebsite\Excel\Excel::XLS);
    //         } else {
    //             Excel::import($import, $file, null, \Maatwebsite\Excel\Excel::XLSX);
    //         }
    //         // ===== END FIX =====

    //         $importedCount = $import->getImportedCount();
    //         $errors = $import->getErrors();
    //         $stats = $import->getStats();

    //         $message = "Successfully imported {$importedCount} suppliers.";

    //         if (!empty($stats)) {
    //             $message .= " Created: {$stats['created']}";
    //             if (isset($stats['skipped'])) {
    //                 $message .= ", Skipped: {$stats['skipped']}";
    //             }
    //         }

    //         if (!empty($errors)) {
    //             return redirect()->route('suppliers.index')
    //                 ->with('warning', $message . " with " . count($errors) . " errors.")
    //                 ->with('import_errors', $errors);
    //         }

    //         return redirect()->route('suppliers.index')
    //             ->with('success', $message);
    //     } catch (\Exception $e) {
    //         Log::error('Supplier import failed: ' . $e->getMessage());
    //         return back()->with('error', 'Failed to import suppliers: ' . $e->getMessage());
    //     }
    // }

    protected function handleBulkUpload(Request $request)
    {
        $request->validate([
            'bulk_file' => 'required|file|max:102400',
        ]);

        try {
            $file = $request->file('bulk_file');

            // Read the file content
            $content = file_get_contents($file->getRealPath());

            // Split by lines
            $lines = explode("\n", $content);

            // Remove empty lines
            $lines = array_filter($lines, function ($line) {
                return !empty(trim($line));
            });

            // Remove header if it looks like a header
            $firstLine = trim($lines[0] ?? '');
            if (
                strpos(strtolower($firstLine), 'supplier') !== false ||
                strpos(strtolower($firstLine), 'name') !== false
            ) {
                array_shift($lines);
            }

            $importedCount = 0;
            $errors = [];
            $skippedCount = 0;
            $rowNumber = 1;

            foreach ($lines as $line) {
                $rowNumber++;
                $supplierName = trim($line);

                // If it's a CSV line, split by comma
                if (strpos($line, ',') !== false) {
                    $parts = str_getcsv($line);
                    $supplierName = trim($parts[0] ?? '');
                }

                if (empty($supplierName)) {
                    $errors[] = "Row {$rowNumber}: Empty supplier name";
                    $skippedCount++;
                    continue;
                }

                // Check if supplier already exists
                $existing = Supplier::where('name', $supplierName)->first();

                if ($existing) {
                    $errors[] = "Row {$rowNumber}: Supplier '{$supplierName}' already exists";
                    $skippedCount++;
                    continue;
                }

                // Create supplier
                Supplier::create([
                    'name' => $supplierName,
                    'order_day' => 'monday',
                ]);

                $importedCount++;
            }

            $message = "Successfully imported {$importedCount} suppliers.";

            if ($skippedCount > 0) {
                $message .= " Skipped: {$skippedCount}";
            }

            if (!empty($errors)) {
                return redirect()->route('suppliers.index')
                    ->with('warning', $message . " with " . count($errors) . " errors.")
                    ->with('import_errors', $errors);
            }

            return redirect()->route('suppliers.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Supplier import failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to import suppliers: ' . $e->getMessage());
        }
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers')->ignore($supplier->id)],
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'order_day' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'is_active' => 'boolean',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier)
    {
        try {
            $supplier->delete();
            return redirect()->route('suppliers.index')
                ->with('success', 'Supplier deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Cannot delete supplier with existing orders or sales data.');
        }
    }

    public function show(Supplier $supplier)
    {
        $supplier->load(['medicines', 'orders' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $accumulatedSales = AccumulatedSale::where('supplier_id', $supplier->id)
            ->where('is_cleared', false)
            ->with('medicine')
            ->get();

        return view('suppliers.show', compact('supplier', 'accumulatedSales'));
    }
}
