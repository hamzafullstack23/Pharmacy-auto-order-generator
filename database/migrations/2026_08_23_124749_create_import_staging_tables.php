<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_uuid')->unique()->index();
            $table->string('status')->default('staged'); // staged, paused, resolved, committed, failed
            $table->date('sale_date');
            $table->string('original_filename');
            $table->json('missing_entities')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->date('sale_date')->nullable();
            $table->string('status')->default('pending'); // pending, resolved, committed, skipped
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};