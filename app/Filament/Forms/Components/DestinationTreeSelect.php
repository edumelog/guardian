<?php

namespace App\Filament\Forms\Components;

use App\Models\Destination;
use Filament\Forms\Components\Field;

class DestinationTreeSelect extends Field
{
    protected string $view = 'filament.forms.components.destination-tree-select';

    public function getDestinations()
    {
        return Destination::whereNull('parent_id')->with('children')->get();
    }
} 