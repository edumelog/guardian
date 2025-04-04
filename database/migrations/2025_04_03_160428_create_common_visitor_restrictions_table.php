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
            $table->enum('severity_level', ['none', 'low', 'medium', 'high'])->default('none');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Índices para otimização
            $table->index(['active', 'expires_at']);
            
            // Cria um índice na coluna visitor_id 
            // Em vez de usar unique(['visitor_id', 'active']) que causaria problemas
            // com múltiplas restrições desativadas, vamos usar um índice simples
            $table->index('visitor_id');
            
            // Não usamos mais esta restrição única que afeta tanto active=true quanto active=false
            // $table->unique(['visitor_id', 'active'], 'unique_active_restriction_per_visitor');
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