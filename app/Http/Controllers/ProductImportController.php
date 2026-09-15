<?php

namespace App\Http\Controllers;

use App\Services\ProductImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ProductImportController extends Controller
{
    protected $importService;

    public function __construct(ProductImportService $importService)
    {
        $this->importService = $importService;
    }

    public function showUploadForm()
    {
        return view('products.upload');
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Extend session lifetime for this request
        Session::put('_token', $request->session()->token());
        
        $file = $request->file('file');
        $filePath = $file->storeAs('imports', 'products_' . time() . '.csv');

        $dryRun = $request->has('dry_run');
        $chunkSize = (int) $request->input('chunk_size', 100);

        // Generate a unique import ID
        $importId = 'import_' . time() . '_' . uniqid();
        session(['import_id' => $importId]);

        // Clear any previous progress
        Cache::forget('import_progress_' . $importId);

        // Set progress callback
        $this->importService->onProgress(function ($stats) use ($importId) {
            Cache::put('import_progress_' . $importId, [
                'progress' => $stats['progress'],
                'processed_rows' => $stats['processed_rows'],
                'total_rows' => $stats['total_rows'],
                'current_chunk' => $stats['current_chunk'],
                'total_chunks' => $stats['total_chunks'],
                'created' => $stats['medicines_created'],
                'updated' => $stats['medicines_updated'],
                'errors' => count($stats['errors']),
                'warnings' => count($stats['warnings']),
                'suppliers_created' => $stats['suppliers_created'],
                'suppliers_updated' => $stats['suppliers_updated'],
                'companies_created' => $stats['companies_created'],
                'companies_updated' => $stats['companies_updated'],
                'duplicates_skipped' => $stats['duplicates_skipped'],
                'is_complete' => false,
                'last_update' => time(),
            ], 3600); // Cache for 1 hour
        });

        try {
            // Increase execution time
            set_time_limit(0);
            
            $result = $this->importService->import(
                Storage::path($filePath), 
                $dryRun, 
                $chunkSize
            );

            // Mark as complete
            $progressData = Cache::get('import_progress_' . $importId, []);
            $progressData['is_complete'] = true;
            $progressData['last_update'] = time();
            Cache::put('import_progress_' . $importId, $progressData, 3600);

        } catch (\Exception $e) {
            Log::error('Import failed: ' . $e->getMessage());
            Cache::put('import_progress_' . $importId, [
                'progress' => 0,
                'processed_rows' => 0,
                'total_rows' => 0,
                'current_chunk' => 0,
                'total_chunks' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 1,
                'warnings' => 0,
                'is_complete' => true,
                'error' => $e->getMessage(),
                'last_update' => time(),
            ], 3600);
        }

        // Clean up the file
        Storage::delete($filePath);

        if ($dryRun) {
            return back()->with('info', 'Dry run completed. See the results below.')
                         ->with('import_stats', $this->importService->formatStats())
                         ->with('import_id', $importId);
        }

        if (empty($result['errors'])) {
            return back()->with('success', 'Products imported successfully!')
                         ->with('import_stats', $this->importService->formatStats())
                         ->with('import_id', $importId);
        }

        return back()->with('error', 'Import completed with errors. Please check the logs.')
                     ->with('import_stats', $this->importService->formatStats())
                     ->with('import_id', $importId);
    }

    /**
     * Get import progress via AJAX
     */
    public function getProgress(Request $request)
    {
        $importId = $request->input('import_id', session('import_id'));
        
        if (!$importId) {
            return response()->json([
                'progress' => 0,
                'processed_rows' => 0,
                'total_rows' => 0,
                'current_chunk' => 0,
                'total_chunks' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'warnings' => 0,
                'is_complete' => false,
                'error' => 'No import in progress',
            ]);
        }

        $progress = Cache::get('import_progress_' . $importId, [
            'progress' => 0,
            'processed_rows' => 0,
            'total_rows' => 0,
            'current_chunk' => 0,
            'total_chunks' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'warnings' => 0,
            'suppliers_created' => 0,
            'suppliers_updated' => 0,
            'companies_created' => 0,
            'companies_updated' => 0,
            'duplicates_skipped' => 0,
            'is_complete' => false,
            'error' => null,
            'last_update' => time(),
        ]);

        // Check if progress is stale (no update for 30 seconds and not complete)
        if (!$progress['is_complete'] && (time() - ($progress['last_update'] ?? 0) > 30)) {
            // Still processing, return current progress
        }

        return response()->json($progress);
    }

    /**
     * Cancel import
     */
    public function cancelImport(Request $request)
    {
        $importId = $request->input('import_id', session('import_id'));
        
        if ($importId) {
            Cache::forget('import_progress_' . $importId);
            session()->forget('import_id');
            
            return response()->json(['success' => true, 'message' => 'Import cancelled']);
        }
        
        return response()->json(['success' => false, 'message' => 'No import to cancel']);
    }
}