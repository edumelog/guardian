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
        Schema::create('common_visitor_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained()->onDelete('cascade');
            $table->text('reason');
            $table->enum('severity_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Índices para otimização
            $table->index(['active', 'expires_at']);
            
            // Garantir que cada visitante só tenha uma restrição ativa por vez
            $table->unique(['visitor_id', 'active'], 'unique_active_restriction_per_visitor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('common_visitor_restrictions');
    }
}; 