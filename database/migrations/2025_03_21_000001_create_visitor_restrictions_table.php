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
        Schema::create('visitor_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name')->nullable(); // Para visitantes não cadastrados
            $table->string('doc')->nullable(); // Para visitantes não cadastrados
            $table->foreignId('doc_type_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('reason');
            $table->enum('severity_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Índices para otimização
            $table->index(['name']);
            $table->index(['doc', 'doc_type_id']);
            $table->index(['active', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_restrictions');
    }
}; 