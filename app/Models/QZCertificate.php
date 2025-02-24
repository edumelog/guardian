<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class QZCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'private_key_path',
        'digital_certificate_path',
        'pfx_password',
    ];

    protected $hidden = [
        'pfx_password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function setPrivateKeyAttribute($value)
    {
        if (!empty($value)) {
            Storage::disk('local')->put('private/private-key.pem', $value);
            $this->attributes['private_key_path'] = 'private/private-key.pem';
        }
    }

    public function setDigitalCertificateAttribute($value)
    {
        if (!empty($value)) {
            Storage::disk('local')->put('private/digital-certificate.txt', $value);
            $this->attributes['digital_certificate_path'] = 'private/digital-certificate.txt';
        }
    }

    public function getPrivateKeyPathFullAttribute(): string
    {
        return storage_path('app/' . $this->private_key_path);
    }

    public function getDigitalCertificatePathFullAttribute(): string
    {
        return storage_path('app/' . $this->digital_certificate_path);
    }
} 