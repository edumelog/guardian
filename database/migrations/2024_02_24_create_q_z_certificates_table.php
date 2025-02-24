<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('q_z_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('private_key_path')->nullable();
            $table->string('digital_certificate_path')->nullable();
            $table->string('pfx_password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('q_z_certificates');
    }
}; 