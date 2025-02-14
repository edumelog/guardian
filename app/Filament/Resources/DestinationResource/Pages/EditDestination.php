<?php

namespace App\Filament\Resources\DestinationResource\Pages;

use App\Filament\Resources\DestinationResource;
use App\Models\Destination;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class EditDestination extends EditRecord
{
    protected static string $resource = DestinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form
            ->schema([
                Section::make('Informações do Destino')
                    ->schema(DestinationResource::form($form)->getComponents()),
                Section::make('Hierarquia de Destinos')
                    ->description('Visualização da estrutura hierárquica')
                    ->schema([
                        Placeholder::make('')
                            ->content(fn () => new HtmlString($this->renderDestinationNode($this->record)))
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private function renderDestinationNode(Destination $destination): string
    {
        $html = '<div class="p-4 bg-gray-50 rounded-lg border border-gray-200">';
        
        // Nome do destino atual
        $html .= '<div class="mb-4">';
        $html .= '<span class="text-xl font-bold text-primary-600">' . $destination->name . '</span>';
        $html .= '</div>';

        // Subdestinos
        $children = $destination->children()->with('children')->get();
        if ($children->isNotEmpty()) {
            $html .= '<div class="mt-2">';
            // $html .= '<div class="text-sm font-medium text-gray-600 mb-2">Subdestinos:</div>';
            $html .= '<div class="space-y-2 ml-4">';
            
            foreach ($children as $child) {
                $html .= '<div class="relative pl-4 border-l-2 border-primary-500">';
                $html .= '<div class="flex items-center gap-2">';
                $html .= '<span class="text-primary-500">└─</span>';
                $html .= '<span class="font-medium">' . $child->name . '</span>';
                $html .= '</div>';
                
                // Renderiza os filhos do subdestino recursivamente
                if ($child->children->isNotEmpty()) {
                    $html .= $this->renderChildrenTree($child->children);
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        } else {
            $html .= '<div class="text-gray-500 italic mt-2">Este destino não possui subdestinos.</div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    private function renderChildrenTree($children, $level = 1): string
    {
        $html = '<div class="space-y-2 ml-4 mt-2">';
        
        foreach ($children as $child) {
            $html .= '<div class="relative pl-4 border-l-2 border-primary-300">';
            $html .= '<div class="flex items-center gap-2">';
            $html .= '<span class="text-primary-400">└─</span>';
            $html .= '<span class="font-medium text-gray-600">' . $child->name . '</span>';
            $html .= '</div>';
            
            // Renderiza os filhos recursivamente
            if ($child->children->isNotEmpty()) {
                $html .= $this->renderChildrenTree($child->children, $level + 1);
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
} 