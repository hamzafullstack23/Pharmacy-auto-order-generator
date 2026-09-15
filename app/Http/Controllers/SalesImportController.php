<?php

namespace App\Http\Controllers;

use App\Imports\DailySalesImport;
use App\Models\DailySale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelType;
use Illuminate\Support\Facades\Storage;

class SalesImportController extends Controller
{
    public function showUploadForm()
    {
        return view('sales.upload');
    }

    public function upload(Request $request)
    {
        Log::info('Upload started', [
            'has_file' => $request->hasFile('file'),
            'file' => $request->file('file') ? $request->file('file')->getClientOriginalName() : 'No file',
            'file_extension' => $request->file('file') ? $request->file('file')->getClientOriginalExtension() : 'No file',
        ]);

        $request->validate([
            'file' => 'required|file|max:102400|mimes:csv,xlsx,xls',
            'sale_date' => 'required|date|before_or_equal:today',
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());

            // Store file
            $storedPath = $file->storeAs(
                'imports',
                'sales_import_' . time() . '.' . $extension,
                'local'
            );

            $fullPath = Storage::disk('local')->path($storedPath);

            $import = new DailySalesImport($request->sale_date);

            // Force CSV reader for CSV files
            if ($extension === 'csv') {
                Excel::import($import, $fullPath, null, ExcelType::CSV);
            } else {
                Excel::import($import, $fullPath);
            }

            // Clean up
            Storage::disk('local')->delete($storedPath);

            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errors = $import->getErrors();

            DB::commit();

            // Check if any records were imported
            if ($importedCount === 0 && $skippedCount === 0) {
                return back()->with('error', 'No data was imported. Please check your file format and column headers. Required columns: Product Code, Product Name, Quantity');
            }

            $message = "Imported {$importedCount} sales records";
            if ($skippedCount > 0) {
                $message .= ", skipped {$skippedCount} records with errors";
            }
            $message .= ".";

            if (!empty($errors)) {
                $displayErrors = array_slice($errors, 0, 50);
                $totalErrors = count($errors);

                return back()
                    ->with('warning', $message . " Found " . $totalErrors . " error(s).")
                    ->with('import_errors', $displayErrors)
                    ->with('total_errors', $totalErrors)
                    ->with('imported_count', $importedCount)
                    ->with('skipped_count', $skippedCount);
            }

            return redirect()->route('sales.upload')
                ->with('success', $message);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Handle validation exceptions specifically
            DB::rollBack();
            Log::error('Sales import validation failed: ' . $e->getMessage());

            $failures = $e->failures();
            $errorMessages = [];

            foreach ($failures as $failure) {
                $errorMessages[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
            }

            if (isset($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            return back()
                ->with('error', 'Validation failed. Please check your file format.')
                ->with('import_errors', array_slice($errorMessages, 0, 50))
                ->with('total_errors', count($errorMessages));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sales import failed: ' . $e->getMessage(), [
                'file' => $request->file('file') ? $request->file('file')->getClientOriginalName() : 'No file',
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            return back()->with('error', 'Failed to import sales data: ' . $e->getMessage());
        }
    }

    public function history()
    {
        $sales = DailySale::with(['company', 'supplier', 'medicine'])
            ->orderBy('sale_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('sales.history', compact('sales'));
    }
}
