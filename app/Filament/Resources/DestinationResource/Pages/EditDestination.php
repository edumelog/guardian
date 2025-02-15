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
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading(fn(): string => 'Deletar Destino')
                ->modalDescription(function (Destination $record): string {
                    $childrenCount = $record->children()->count();
                    if ($childrenCount > 0) {
                        return "O destino \"{$record->name}\" possui {$childrenCount} subdestino(s) associado(s). Ao deletar este destino, todos os subdestinos também serão removidos. Deseja continuar?";
                    }
                    return "Tem certeza que deseja deletar o destino \"{$record->name}\"?";
                })
                ->modalSubmitActionLabel('Sim, deletar'),
        ];
    }

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form
            ->schema([
                Section::make('Estrutura Hierárquica')
                    ->schema([
                        Placeholder::make('')
                            ->content(fn () => new HtmlString($this->renderDestinationNode($this->record)))
                    ])
                    ->columnSpanFull(),
                Section::make('Informações do Destino')
                    ->schema(DestinationResource::form($form)->getComponents()),
            ]);
    }

    private function renderDestinationNode(Destination $destination): string
    {
        $html = '<div class="p-4 bg-gray-50 rounded-lg border border-gray-200">';
        
        // Renderiza a hierarquia superior (pais)
        $parentHierarchy = $this->getParentHierarchy($destination);
        if (!empty($parentHierarchy)) {
            $html .= '<div class="space-y-2">';
            
            // Primeiro destino (raiz)
            $html .= '<div class="flex items-center">';
            $html .= '<span class="font-medium text-gray-600">' . $parentHierarchy[0]->name . '</span>';
            $html .= '</div>';
            
            // Demais destinos da hierarquia
            for ($i = 1; $i < count($parentHierarchy); $i++) {
                $html .= '<div class="flex items-center" style="padding-left: ' . ($i * 24) . 'px">';
                $html .= '<span class="text-gray-400">└─</span>';
                $html .= '<span class="font-medium text-gray-600">' . $parentHierarchy[$i]->name . '</span>';
                $html .= '</div>';
            }
            
            // Destino atual
            $html .= '<div class="flex items-center" style="padding-left: ' . (count($parentHierarchy) * 24) . 'px">';
            $html .= '<span class="text-gray-400">└─</span>';
            $html .= '<span class="font-medium text-primary-600">' . $destination->name . '</span>';
            $html .= '</div>';
            
            $html .= '</div>';
        } else {
            // Se não tiver pais, mostra apenas o destino atual
            $html .= '<div class="mb-4">';
            $html .= '<span class="text-xl font-bold text-primary-600">' . $destination->name . '</span>';
            $html .= '</div>';
        }

        // Subdestinos
        $children = $destination->children()->with('children')->get();
        if ($children->isNotEmpty()) {
            $baseLevel = !empty($parentHierarchy) ? count($parentHierarchy) + 1 : 1;
            $html .= $this->renderChildrenTree($children, $baseLevel);
        } else {
            $html .= '<div class="text-gray-500 italic mt-2">Este destino não possui subdestinos.</div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    private function getParentHierarchy(Destination $destination): array
    {
        $hierarchy = [];
        $current = $destination->parent;
        
        while ($current !== null) {
            array_unshift($hierarchy, $current);
            $current = $current->parent;
        }
        
        return $hierarchy;
    }

    private function renderChildrenTree($children, $level = 1): string
    {
        $html = '<div class="space-y-2 mt-2">';
        
        foreach ($children as $child) {
            $html .= '<div class="flex items-center" style="padding-left: ' . ($level * 24) . 'px">';
            $html .= '<span class="text-gray-400">└─</span>';
            $html .= '<span class="font-medium text-gray-600">' . $child->name . '</span>';
            $html .= '</div>';
            
            if ($child->children->isNotEmpty()) {
                $html .= $this->renderChildrenTree($child->children, $level + 1);
            }
        }
        
        $html .= '</div>';
        return $html;
    }
} 