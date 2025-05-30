<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo para representação personalizada dos dias da semana
 * 
 * Este modelo permite associar imagens ou textos a cada dia da semana,
 * que serão usados no processo de substituição de marcadores em templates.
 */
class WeekDay extends Model
{
    use HasFactory;
    
    /**
     * Atributos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'day_number',
        'image',
        'text_value',
        'is_active',
    ];
    
    /**
     * Conversão de tipos para atributos
     */
    protected $casts = [
        'is_active' => 'boolean',
        'day_number' => 'integer',
    ];
    
    /**
     * Lista de dias da semana
     */
    public const WEEK_DAYS = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];
    
    /**
     * Retorna a URL da imagem
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }
        
        return route('weekday.image', ['filename' => basename($this->image)]);
    }
    
    /**
     * Retorna o texto formatado em maiúsculo
     */
    public function getFormattedTextAttribute(): ?string
    {
        if (!$this->text_value) {
            return null;
        }
        
        return strtoupper($this->text_value);
    }
    
    /**
     * Retorna o WeekDay para o dia atual
     */
    public static function getCurrentDay()
    {
        $currentDayNumber = now()->dayOfWeek;
        return self::where('day_number', $currentDayNumber)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Quando o modelo for atualizado e a imagem for alterada, remove a imagem antiga
        static::updating(function ($weekDay) {
            if ($weekDay->isDirty('image') && $weekDay->getOriginal('image')) {
                Storage::disk('public')->delete($weekDay->getOriginal('image'));
            }
        });
    }
    
    /**
     * Retorna o WeekDay para uma data específica
     */
    public static function getDayByDate($date)
    {
        $dayNumber = $date->dayOfWeek;
        return self::where('day_number', $dayNumber)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Retorna o nome padrão do dia da semana
     */
    public function getDayNameAttribute(): string
    {
        return self::WEEK_DAYS[$this->day_number] ?? 'Desconhecido';
    }
}
