// database/migrations/2024_01_01_000003_create_medicine_supplier_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('medicine_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_medicine_code')->nullable();
            $table->decimal('supplier_price', 10, 2)->nullable();
            $table->enum('pack_type', ['pack', 'loose'])->default('loose');
            $table->integer('pack_size')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            $table->unique(['medicine_id', 'supplier_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('medicine_supplier');
    }
};