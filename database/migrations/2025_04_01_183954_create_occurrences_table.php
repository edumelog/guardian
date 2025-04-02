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
        Schema::create('occurrences', function (Blueprint $table) {
            $table->id();
            $table->text('description')->comment('Descrição da ocorrência');
            $table->enum('severity', ['green', 'amber', 'red'])->comment('Severidade: Verde, Âmbar ou Vermelho');
            $table->timestamp('occurrence_datetime')->comment('Data e hora da ocorrência');
            $table->unsignedBigInteger('created_by')->comment('ID do usuário que criou o registro');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('ID do usuário que atualizou o registro');
            $table->timestamps();
            
            // Chaves estrangeiras
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
        
        // Tabela pivot para relacionar ocorrências com visitantes
        Schema::create('occurrence_visitor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('visitor_id');
            $table->timestamps();
            
            $table->foreign('occurrence_id')->references('id')->on('occurrences')->onDelete('cascade');
            $table->foreign('visitor_id')->references('id')->on('visitors')->onDelete('cascade');
            
            $table->unique(['occurrence_id', 'visitor_id']);
        });
        
        // Tabela pivot para relacionar ocorrências com destinos
        Schema::create('occurrence_destination', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('destination_id');
            $table->timestamps();
            
            $table->foreign('occurrence_id')->references('id')->on('occurrences')->onDelete('cascade');
            $table->foreign('destination_id')->references('id')->on('destinations')->onDelete('cascade');
            
            $table->unique(['occurrence_id', 'destination_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('occurrence_visitor');
        Schema::dropIfExists('occurrence_destination');
        Schema::dropIfExists('occurrences');
    }
};
