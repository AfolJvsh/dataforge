<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->timestampTz('retention_until')->nullable()->index()->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('imports', fn (Blueprint $table) => $table->dropColumn('retention_until'));
    }
};
