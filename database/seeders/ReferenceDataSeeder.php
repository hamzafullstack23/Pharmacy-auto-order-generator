<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Table seeding order — respects FK dependencies.
     */
    protected array $tables = [
        'suppliers' => [
            'uniqueBy' => ['id'],
            'nullable' => ['contact_person', 'email', 'phone', 'address', 'order_day'],
        ],
        'companies' => [
            'uniqueBy' => ['id'],
            'nullable' => ['contact_person', 'email', 'phone', 'address', 'supplier_id'],
        ],
        'medicines' => [
            'uniqueBy' => ['id'],
            'nullable' => ['product_code', 'unit', 'cost', 'pack_size', 'max_stock_limit'],
        ],
        'medicine_supplier' => [
            'uniqueBy' => ['medicine_id', 'supplier_id'],
            'nullable' => ['supplier_medicine_code', 'supplier_price', 'pack_type', 'pack_size'],
        ],
    ];

    public function run(): void
    {
        foreach ($this->tables as $table => $config) {
            $this->seedTable($table, $config);
        }

        foreach (array_keys($this->tables) as $table) {
            $this->resetAutoIncrement($table);
        }

        $this->command->info('Reference data seeded successfully.');
    }

    protected function seedTable(string $table, array $config): void
    {
        $path = database_path("seeders/data/{$table}.json");

        if (! File::exists($path)) {
            $this->command->warn("Skipping '{$table}': file not found at {$path}");
            return;
        }

        $raw = json_decode(File::get($path), true);

        if (! is_array($raw)) {
            $this->command->warn("Skipping '{$table}': invalid JSON.");
            return;
        }

        $rows = $this->extractRows($raw);

        if (empty($rows)) {
            $this->command->warn("Skipping '{$table}': no data rows found.");
            return;
        }

        $this->command->info("Seeding '{$table}' (" . count($rows) . " rows)...");

        $bar = $this->command->getOutput()->createProgressBar(count($rows));
        $bar->start();

        DB::transaction(function () use ($table, $rows, $config, $bar) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                foreach (array_chunk($rows, 500) as $chunk) {
                    foreach ($chunk as $row) {
                        $row  = $this->prepareRow($row, $config);
                        $keys = $this->uniqueKeys($row, $config['uniqueBy']);

                        DB::table($table)->updateOrInsert($keys, $row);
                        $bar->advance();
                    }
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        $bar->finish();
        $this->command->newLine();
        $this->command->info("  ✓ '{$table}' seeded.");
    }

    /**
     * Extract row arrays from a JSON payload.
     *
     * Supports three shapes:
     *   1. phpMyAdmin wrapper:  [ {type:header}, {type:table, data:[...rows]} ]
     *   2. Plain array:         [ {id:1,...}, {id:2,...} ]
     *   3. Wrapped object:      { "data": [ {id:1,...}, ... ] }
     */
    protected function extractRows(array $raw): array
    {
        // Shape 1: phpMyAdmin export — find the element with type = "table"
        // and pull its `data` array.
        foreach ($raw as $entry) {
            if (
                is_array($entry)
                && ($entry['type'] ?? null) === 'table'
                && isset($entry['data'])
                && is_array($entry['data'])
            ) {
                return $entry['data'];
            }
        }

        // Shape 3: object with a top-level "data" key
        if (isset($raw['data']) && is_array($raw['data'])) {
            return $raw['data'];
        }

        // Shape 2: plain array of row objects
        $first = $raw[0] ?? null;
        if (is_array($first) && ! array_key_exists('type', $first)) {
            return $raw;
        }

        return [];
    }

    protected function uniqueKeys(array $row, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $result[$key] = $row[$key];
            }
        }
        return $result;
    }

    protected function prepareRow(array $row, array $config): array
    {
        foreach ($config['nullable'] ?? [] as $field) {
            if (array_key_exists($field, $row) && $row[$field] === '') {
                $row[$field] = null;
            }
        }
        return $row;
    }

    protected function resetAutoIncrement(string $table): void
    {
        try {
            $maxId = DB::table($table)->max('id') ?? 0;
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = " . ($maxId + 1));
        } catch (\Throwable $e) {
            // Silently ignore if the driver doesn't support it
        }
    }
}