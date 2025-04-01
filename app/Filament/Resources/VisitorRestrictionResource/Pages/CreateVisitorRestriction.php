<?php

namespace App\Filament\Resources\VisitorRestrictionResource\Pages;

use Filament\Tables;
use App\Models\Visitor;
use Filament\Tables\Table;
use Filament\Support\Enums\MaxWidth;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\VisitorRestrictionResource;

class CreateVisitorRestriction extends ListRecords
{
    protected static string $resource = VisitorRestrictionResource::class;

    // Set the page title
    protected static ?string $title = 'Criar Restrição';
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    public function table(Table $table): Table
    {
        return $table
            ->query(Visitor::query())
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->getStateUsing(fn (Visitor $record): ?string => 
                        $record->photo ? route('visitor.photo', ['filename' => $record->photo]) : null
                    )
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('docType.type')
                    ->label('Tipo de Documento')
                    ->sortable(),
                Tables\Columns\TextColumn::make('doc')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('destination.name')
                    ->label('Destino')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Entrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('doc_type_id')
                    ->relationship('docType', 'type')
                    ->label('Tipo de Documento')
                    ->preload()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('destination_id')
                    ->relationship('destination', 'name')
                    ->label('Destino')
                    ->preload()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('create_restriction')
                    ->label('Criar Restrição')
                    ->icon('heroicon-m-shield-exclamation')
                    ->url(fn (Visitor $record): string => VisitorRestrictionResource::getUrl('new', ['visitor_id' => $record->id])),
            ])
            ->bulkActions([])
            ->paginated([10, 25, 50])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null);
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.dashboard.resources.visitor-restrictions.index') => 'Restrições',
            '#' => 'Criar',
        ];
    }
}
