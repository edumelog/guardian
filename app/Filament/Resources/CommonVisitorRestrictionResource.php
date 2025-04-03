<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommonVisitorRestrictionResource\Pages;
use App\Filament\Resources\CommonVisitorRestrictionResource\RelationManagers;
use App\Models\CommonVisitorRestriction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommonVisitorRestrictionResource extends Resource 
{
    protected static ?string $model = CommonVisitorRestriction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Análise de Segurança';

    protected static ?string $navigationLabel = 'Restrições Comuns';

    protected static ?string $pluralModelLabel = 'Restrições Comuns';

    protected static ?string $modelLabel = 'Restrição Comum';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommonVisitorRestrictions::route('/'),
            'create' => Pages\CreateCommonVisitorRestriction::route('/create'),
            'edit' => Pages\EditCommonVisitorRestriction::route('/{record}/edit'),
        ];
    }
}
