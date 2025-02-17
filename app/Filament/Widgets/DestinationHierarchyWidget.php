<?php

namespace App\Filament\Widgets;

use App\Models\Destination;
use Filament\Widgets\Widget;
use Illuminate\Support\HtmlString;

class DestinationHierarchyWidget extends Widget
{
    protected static string $view = 'filament.widgets.destination-hierarchy-widget';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return request()->routeIs('filament.admin.resources.destinations.index');
    }

    protected function getViewData(): array
    {
        return [
            'tree' => $this->buildTree(),
        ];
    }

    private function buildTree()
    {
        // Carrega todos os destinos com seus relacionamentos
        $destinations = Destination::with('children')->get();
        
        // Retorna apenas os nós raiz (sem pai)
        return $destinations->whereNull('parent_id');
    }

    private function renderHierarchyTree(): string
    {
        $rootDestinations = Destination::whereNull('parent_id')->with('children')->get();
        return $this->renderHierarchyNodes($rootDestinations);
    }

    private function renderHierarchyNodes($destinations, $level = 0): string
    {
        if ($destinations->isEmpty()) {
            return '';
        }

        $html = '<div class="space-y-2">';
        
        foreach ($destinations as $destination) {
            $hasChildren = $destination->children->isNotEmpty();
            $padding = $level * 24;
            
            $html .= '<div class="hierarchy-item">';
            $html .= '<div class="flex items-center gap-2" style="padding-left: ' . $padding . 'px">';
            
            if ($hasChildren) {
                $html .= '<button type="button" class="toggle-children" data-id="' . e($destination->id) . '">';
                $html .= '<svg class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>';
                $html .= '</svg>';
                $html .= '</button>';
            } else {
                $html .= '<span class="w-4"></span>';
            }
            
            $html .= '<span class="font-medium text-gray-600">' . e($destination->name) . '</span>';
            $html .= '</div>';
            
            if ($hasChildren) {
                $html .= '<div class="children hidden" data-parent="' . e($destination->id) . '">';
                $html .= $this->renderHierarchyNodes($destination->children, $level + 1);
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';

        // Adiciona o JavaScript apenas uma vez, no final do primeiro nível
        if ($level === 0) {
            $html .= '<script>';
            $html .= 'document.addEventListener("DOMContentLoaded", function() {';
            $html .= '    document.querySelectorAll(".toggle-children").forEach(function(button) {';
            $html .= '        button.addEventListener("click", function(e) {';
            $html .= '            e.preventDefault();';
            $html .= '            var destinationId = this.getAttribute("data-id");';
            $html .= '            var childrenContainer = document.querySelector("[data-parent=\"" + destinationId + "\"]");';
            $html .= '            var icon = this.querySelector("svg");';
            $html .= '            childrenContainer.classList.toggle("hidden");';
            $html .= '            icon.style.transform = childrenContainer.classList.contains("hidden") ? "rotate(0deg)" : "rotate(90deg)";';
            $html .= '        });';
            $html .= '    });';
            $html .= '});';
            $html .= '</script>';
            $html .= '<style>';
            $html .= '.hierarchy-item { margin-bottom: 0.5rem; }';
            $html .= '.toggle-children { cursor: pointer; padding: 2px; border-radius: 4px; transition: background-color 0.2s; }';
            $html .= '.toggle-children:hover { background-color: rgb(243 244 246); }';
            $html .= '.children { margin-top: 0.5rem; }';
            $html .= '</style>';
        }

        return $html;
    }
} 