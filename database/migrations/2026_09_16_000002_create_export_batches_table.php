<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_batches', function (Blueprint $table) {
            $table->id();
            $table->string('export_uuid')->unique()->index();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name');
            $table->integer('rows_count')->default(0);
            $table->decimal('total_quantity', 14, 2)->default(0);
            $table->string('filename');
            $table->timestamps();
        });

        Schema::table('import_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('import_rows', 'export_batch_id')) {
                $table->foreignId('export_batch_id')
                    ->nullable()
                    ->after('exported_at')
                    ->constrained('export_batches')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('export_batch_id');
        });
        Schema::dropIfExists('export_batches');
    }
};