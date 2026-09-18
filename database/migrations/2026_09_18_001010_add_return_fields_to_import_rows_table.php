<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('import_rows', 'return_reason')) {
                $table->string('return_reason')
                    ->nullable()
                    ->after('exported_at');
            }

            if (!Schema::hasColumn('import_rows', 'returned_at')) {
                $table->timestamp('returned_at')
                    ->nullable()
                    ->after('return_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            if (Schema::hasColumn('import_rows', 'returned_at')) {
                $table->dropColumn('returned_at');
            }

            if (Schema::hasColumn('import_rows', 'return_reason')) {
                $table->dropColumn('return_reason');
            }
        });
    }
};