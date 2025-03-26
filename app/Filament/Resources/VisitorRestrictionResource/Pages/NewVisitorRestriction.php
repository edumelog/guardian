<?php

namespace App\Filament\Resources\VisitorRestrictionResource\Pages;

use App\Filament\Resources\VisitorRestrictionResource;
use App\Models\Visitor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class NewVisitorRestriction extends CreateRecord
{
    protected static string $resource = VisitorRestrictionResource::class;

    public function mount(): void
    {
        parent::mount();

        Log::info('NewVisitorRestriction::mount - visitor_id from request', [
            'visitor_id' => request()->query('visitor_id')
        ]);

        $visitor = Visitor::with(['docType', 'destination'])->findOrFail(request()->query('visitor_id'));
        
        Log::info('NewVisitorRestriction::mount - visitor data', [
            'visitor' => $visitor->toArray()
        ]);

        $this->form->fill([
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
            'active' => true,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Log::info('NewVisitorRestriction::mutateFormDataBeforeCreate - input data', [
            'data' => $data
        ]);

        $visitor = Visitor::findOrFail($data['visitor_id']);
        
        $result = array_merge($data, [
            'name' => $visitor->name,
            'doc' => $visitor->doc,
            'doc_type_id' => $visitor->doc_type_id,
            'created_by' => Auth::id(),
        ]);

        Log::info('NewVisitorRestriction::mutateFormDataBeforeCreate - result data', [
            'result' => $result
        ]);

        return $result;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
} 