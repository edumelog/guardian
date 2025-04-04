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
        Schema::create('predictive_visitor_restrictions', function (Blueprint $table) {
            $table->id();
            $table->string('name_pattern')->nullable()->comment('Padrão de nome com wildcards (* ou ?)');
            $table->boolean('any_document_type')->default(true)->comment('Se true, qualquer tipo de documento');
            $table->json('document_types')->nullable()->comment('Tipos de documentos específicos a restringir em formato JSON');
            $table->string('document_number_pattern')->nullable()->comment('Padrão de número de documento com wildcards (* ou ?)');
            $table->boolean('any_destination')->default(true)->comment('Se true, qualquer destino');
            $table->json('destinations')->nullable()->comment('Destinos específicos a restringir em formato JSON');
            $table->text('reason')->comment('Motivo da restrição preditiva');
            $table->enum('severity_level', ['none', 'low', 'medium', 'high'])->default('medium');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_occurrence')->default(true)->comment('Se true, gera ocorrência automaticamente');
            $table->timestamps();
            
            // Índices para otimização de consulta
            $table->index(['active', 'expires_at']);
            $table->index('severity_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictive_visitor_restrictions');
    }
};
