<?php
namespace App\Console\Commands;

use App\Services\ProductImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ImportProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:import {file : Path to the CSV file}
                            {--dry-run : Validate without making changes}
                            {--chunk=100 : Number of rows to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from CSV file with supplier, company, and medicine mapping';

    /**
     * Execute the console command.
     */
    public function handle(ProductImportService $importService)
    {
        $filePath = $this->argument('file');
        $dryRun = $this->option('dry-run');
        
        // Check if file exists
        if (!file_exists($filePath) && !Storage::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        // Get full path
        if (Storage::exists($filePath)) {
            $filePath = Storage::path($filePath);
        }

        $this->info("Starting product import...");
        $this->line("File: {$filePath}");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE: No changes will be made to the database.");
            $this->newLine();
        }

        // Start the import
        $startTime = microtime(true);
        
        $result = $importService->import($filePath, $dryRun);
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        // Display results
        $this->newLine();
        $this->line($importService->formatStats());
        $this->newLine();
        $this->line("Execution time: {$executionTime} seconds");

        if ($dryRun) {
            $this->info("Dry run completed successfully!");
        } else {
            if (empty($result['errors'])) {
                $this->info("Import completed successfully!");
            } else {
                $this->error("Import completed with errors. Please check the logs.");
                return 1;
            }
        }

        return 0;
    }
}