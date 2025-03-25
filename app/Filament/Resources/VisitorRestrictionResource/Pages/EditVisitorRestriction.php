<?php

namespace App\Filament\Resources\VisitorRestrictionResource\Pages;

use App\Filament\Resources\VisitorRestrictionResource;
use App\Models\Visitor;
use App\Models\VisitorRestriction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVisitorRestriction extends EditRecord
{
    protected static string $resource = VisitorRestrictionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $visitor = Visitor::findOrFail(request()->route('record'));
        
        return array_merge($data, [
            'visitor_id' => $visitor->id,
            'name' => $visitor->name,
            'doc' => $visitor->doc,
            'doc_type_id' => $visitor->doc_type_id,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
