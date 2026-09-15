// database/migrations/2024_01_01_000004_create_medicines_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('unit')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->enum('pack_type', ['pack', 'loose'])->default('loose');
            $table->integer('pack_size')->nullable()->comment('Number of units per pack');
            $table->integer('max_stock_limit')->nullable();
            $table->integer('current_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Medicine names can be duplicated across different companies
            $table->unique(['name', 'company_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('medicines');
    }
};