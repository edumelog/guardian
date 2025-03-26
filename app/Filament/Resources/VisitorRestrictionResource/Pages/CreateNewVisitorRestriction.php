<?php

namespace App\Filament\Resources\VisitorRestrictionResource\Pages;

use App\Filament\Resources\VisitorRestrictionResource;
use App\Filament\Resources\PartialVisitorRestrictionResource;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;

class CreateNewVisitorRestriction extends Page
{
    protected static string $resource = VisitorRestrictionResource::class;
    
    protected static string $view = 'filament.pages.redirect';
    
    public function mount(): void
    {
        Notification::make()
            ->title('Redirecionando...')
            ->body('Você será redirecionado para o formulário de criação de restrições parciais.')
            ->info()
            ->send();
            
        $this->redirect(PartialVisitorRestrictionResource::getUrl('create'));
    }
}
