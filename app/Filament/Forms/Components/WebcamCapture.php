<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;

class WebcamCapture extends Field
{
    protected string $view = 'filament.forms.components.webcam-capture';

    public function getStorageDirectory(): string
    {
        return 'visitors-photos';
    }
} 