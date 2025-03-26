<?php

namespace App\Filament\Resources\VisitorRestrictionResource\Pages;

use App\Filament\Resources\VisitorRestrictionResource;
use App\Models\Visitor;
use App\Models\VisitorRestriction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditVisitorRestriction extends EditRecord
{
    protected static string $resource = VisitorRestrictionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $visitor = Visitor::findOrFail($data['visitor_id']);
        
        return array_merge($data, [
            'name' => $visitor->name,
            'doc' => $visitor->doc,
            'doc_type_id' => $visitor->doc_type_id,
            'created_by' => Auth::id(),
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

    public function mount($record): void
    {
        parent::mount($record);

        $visitor = Visitor::with(['docType', 'destination'])->findOrFail($this->record->visitor_id);
        
        $this->form->fill([
            'severity_level' => $this->record->severity_level,
            'reason' => $this->record->reason,
            'expires_at' => $this->record->expires_at,
            'active' => $this->record->active,
            'visitor_id' => $visitor->id,
            'visitor_name' => $visitor->name,
            'visitor_doc' => $visitor->doc,
            'visitor_doc_type' => $visitor->docType->type,
            'visitor_destination' => $visitor->destination->name,
            'visitor_phone' => $visitor->phone,
            'visitor_last_visit' => $visitor->created_at->format('d/m/Y H:i'),
            'visitor_photo' => $visitor->photo,
            'visitor_doc_photo_front' => $visitor->doc_photo_front,
            'visitor_doc_photo_back' => $visitor->doc_photo_back,
        ]);
    }
}
