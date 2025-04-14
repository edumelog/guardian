<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Define o valor padrão para is_active
     */
    protected $attributes = [
        'is_active' => true,
    ];

    public function printers(): BelongsToMany
    {
        return $this->belongsToMany(Printer::class, 'printer_operator', 'operator_id', 'printer_id')
            ->withTimestamps();
    }

    public function visitorLogs()
    {
        return $this->hasMany(VisitorLog::class, 'operator_id');
    }

    /**
     * Ocorrências criadas pelo usuário
     */
    public function createdOccurrences()
    {
        return $this->hasMany(Occurrence::class, 'created_by');
    }
    
    /**
     * Ocorrências modificadas pelo usuário
     */
    public function updatedOccurrences()
    {
        return $this->hasMany(Occurrence::class, 'updated_by');
    }
    
    /**
     * Verifica se o usuário possui ocorrências associadas
     */
    public function hasRelatedOccurrences(): bool
    {
        return $this->createdOccurrences()->exists() || $this->updatedOccurrences()->exists();
    }
}
