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
            $table->string('partial_name')->nullable()->comment('Pode conter wildcards * e ?');
            $table->string('partial_doc')->nullable()->comment('Pode conter wildcards * e ?');
            $table->foreignId('doc_type_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('phone')->nullable()->comment('Pode conter wildcards * e ?');
            $table->text('reason');
            $table->enum('severity_level', ['low', 'medium', 'high'])->default('low');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Índices para otimização
            $table->index(['partial_name']);
            $table->index(['partial_doc', 'doc_type_id']);
            $table->index(['active', 'expires_at']);
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