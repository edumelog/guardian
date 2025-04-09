<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cria a tabela para armazenar representações personalizadas dos dias da semana
     */
    public function up(): void
    {
        Schema::create('week_days', function (Blueprint $table) {
            $table->id();
            $table->integer('day_number')->unique()->comment('0-Domingo, 1-Segunda, 2-Terça, 3-Quarta, 4-Quinta, 5-Sexta, 6-Sábado');
            $table->string('image')->nullable()->comment('Caminho da imagem representativa');
            $table->string('text_value')->nullable()->comment('Texto representativo (em maiúsculo)');
            $table->boolean('is_active')->default(true)->comment('Status do registro');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('week_days');
    }
};
