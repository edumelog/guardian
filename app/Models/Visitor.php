<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'doc',
        'photo',
        'other',
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
            'other' => ['nullable', 'string', 'max:255'],
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

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
