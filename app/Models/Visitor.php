<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'doc',
        'photo',
        'doc_photo_front',
        'doc_photo_back',
        'other',
        'phone',
        'destination_id',
        'doc_type_id'
    ];

    public static function validationRules($record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'doc' => [
                'required',
                'string',
                'max:255',
                Rule::unique('visitors', 'doc')
                    ->where('doc_type_id', request('doc_type_id'))
                    ->ignore($record)
            ],
            'photo' => ['required', 'string'],
            'doc_photo_front' => ['required', 'string'],
            'doc_photo_back' => ['required', 'string'],
            'other' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'destination_id' => ['required', 'exists:destinations,id'],
            'doc_type_id' => ['required', 'exists:doc_types,id'],
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function docType(): BelongsTo
    {
        return $this->belongsTo(DocType::class);
    }

    public function visitorLogs(): HasMany
    {
        return $this->hasMany(VisitorLog::class);
    }

    public function latestLog(): BelongsTo
    {
        return $this->belongsTo(VisitorLog::class)->latestOfMany();
    }

    /**
     * Retorna a URL para a foto do visitante
     * 
     * @return string|null
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }

        return route('visitor.photo', ['filename' => $this->photo]);
    }

    /**
     * Retorna a URL para a foto frontal do documento do visitante
     * 
     * @return string|null
     */
    public function getDocPhotoFrontUrlAttribute(): ?string
    {
        if (!$this->doc_photo_front) {
            return null;
        }

        return route('visitor.photo', ['filename' => $this->doc_photo_front]);
    }

    /**
     * Retorna a URL para a foto traseira do documento do visitante
     * 
     * @return string|null
     */
    public function getDocPhotoBackUrlAttribute(): ?string
    {
        if (!$this->doc_photo_back) {
            return null;
        }

        return route('visitor.photo', ['filename' => $this->doc_photo_back]);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($visitor) {
            // Cria um novo log de visita quando o visitante é cadastrado
            $visitor->visitorLogs()->create([
                'in_date' => now(),
                'destination_id' => $visitor->destination_id,
                'operator_id' => Auth::id()
            ]);
        });

        static::deleting(function ($visitor) {
            // Remove os arquivos de foto quando o visitante é excluído
            if ($visitor->photo) {
                Storage::disk('private')->delete('visitors-photos/' . $visitor->photo);
            }
            if ($visitor->doc_photo_front) {
                Storage::disk('private')->delete('visitors-photos/' . $visitor->doc_photo_front);
            }
            if ($visitor->doc_photo_back) {
                Storage::disk('private')->delete('visitors-photos/' . $visitor->doc_photo_back);
            }
        });
    }
}
