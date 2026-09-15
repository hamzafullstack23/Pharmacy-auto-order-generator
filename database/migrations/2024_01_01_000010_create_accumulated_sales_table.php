// database/migrations/2024_01_01_000006_create_accumulated_sales_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('accumulated_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->integer('total_quantity')->default(0);
            $table->date('accumulation_start_date');
            $table->date('accumulation_end_date');
            $table->boolean('is_cleared')->default(false);
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('cleared_by_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['supplier_id', 'company_id', 'medicine_id', 'accumulation_start_date', 'accumulation_end_date'], 'unique_accumulation_period');
        });
    }

    public function down()
    {
        Schema::dropIfExists('accumulated_sales');
    }
};