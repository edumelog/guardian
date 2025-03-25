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
        Schema::table('visitors', function (Blueprint $table) {
            $table->boolean('has_restrictions')->default(false)->after('doc_type_id');
            $table->index('has_restrictions'); // Índice para otimizar consultas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropIndex(['has_restrictions']);
            $table->dropColumn('has_restrictions');
        });
    }
}; 