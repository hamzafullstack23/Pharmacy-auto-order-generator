// database/migrations/2024_01_01_000005_create_daily_sales_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daily_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date');
            $table->string('medicine_name');
            $table->integer('quantity_sold');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('import_batch')->nullable();
            $table->timestamps();
            
            // Prevent duplicate entries for same company-medicine on same day
            $table->unique(['sale_date', 'medicine_name', 'company_id'], 'unique_daily_sale');
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_sales');
    }
};