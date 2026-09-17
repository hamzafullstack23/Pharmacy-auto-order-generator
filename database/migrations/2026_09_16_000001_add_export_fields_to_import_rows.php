<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('import_rows', 'is_exported')) {
                $table->boolean('is_exported')->default(false)->after('status');
            }
            if (!Schema::hasColumn('import_rows', 'exported_at')) {
                $table->timestamp('exported_at')->nullable()->after('is_exported');
            }
            $table->index(['supplier_id', 'is_exported'], 'import_rows_supplier_exported_idx');
            $table->index('exported_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropIndex('import_rows_supplier_exported_idx');
            $table->dropIndex(['exported_at']);
            $table->dropColumn(['is_exported', 'exported_at']);
        });
    }
};