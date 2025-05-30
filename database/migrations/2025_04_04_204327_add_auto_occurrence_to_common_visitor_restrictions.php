<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('common_visitor_restrictions', function (Blueprint $table) {
            $table->boolean('auto_occurrence')->default(false)->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('common_visitor_restrictions', function (Blueprint $table) {
            $table->dropColumn('auto_occurrence');
        });
    }
};
