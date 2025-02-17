<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('activity_logs', 'visitor_logs');
    }

    public function down(): void
    {
        Schema::rename('visitor_logs', 'activity_logs');
    }
}; 