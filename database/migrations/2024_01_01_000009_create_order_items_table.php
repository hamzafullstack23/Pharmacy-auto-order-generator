// database/migrations/2024_01_01_000007_create_order_items_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->enum('pack_type', ['pack', 'loose'])->default('loose');
            $table->integer('pack_size')->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['order_id', 'medicine_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_items');
    }
};