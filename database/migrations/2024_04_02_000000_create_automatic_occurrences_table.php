<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatic_occurrences', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Chave única para identificar a ocorrência (ex: doc_expired)
            $table->string('title'); // Título da ocorrência
            $table->text('description'); // Descrição da ocorrência
            $table->boolean('enabled')->default(false); // Status da ocorrência
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatic_occurrences');
    }
}; 