<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_row_errors', function (Blueprint $table) {
            $table->char('error_fingerprint', 64)->nullable()->after('message');
            $table->unique(['execution_id', 'error_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('import_row_errors', function (Blueprint $table) {
            $table->dropUnique(['execution_id', 'error_fingerprint']);
            $table->dropColumn('error_fingerprint');
        });
    }
};
