<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->string('doc_photo_front')->nullable()->after('photo');
            $table->string('doc_photo_back')->nullable()->after('doc_photo_front');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn(['doc_photo_front', 'doc_photo_back']);
        });
    }
}; 