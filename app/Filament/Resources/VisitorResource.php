<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitorResource\Pages;
use App\Filament\Resources\VisitorResource\RelationManagers;
use App\Models\Visitor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use App\Filament\Forms\Components\WebcamCapture;

class VisitorResource extends Resource
{
    protected static ?string $model = Visitor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    
    protected static ?string $navigationGroup = 'Controle de Acesso';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Visitante';
    
    protected static ?string $pluralModelLabel = 'Visitantes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informações do Visitante')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\Select::make('doc_type_id')
                            ->label('Tipo de Documento')
                            ->relationship('docType', 'type')
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('type')
                                    ->label('Tipo')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                            
                        Forms\Components\TextInput::make('doc')
                            ->label('Número do Documento')
                            ->required()
                            ->maxLength(255),
                            
                        WebcamCapture::make('photo')
                            ->label('Foto'),
                            
                        Forms\Components\Select::make('destination_id')
                            ->label('Destino')
                            ->relationship('destination', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Textarea::make('other')
                            ->label('Informações Adicionais')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('docType.type')
                    ->label('Tipo de Documento')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('doc')
                    ->label('Documento')
                    ->searchable(),
                    
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular(),
                    
                Tables\Columns\TextColumn::make('destination.name')
                    ->label('Destino')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListVisitors::route('/'),
            'create' => Pages\CreateVisitor::route('/create'),
            'edit' => Pages\EditVisitor::route('/{record}/edit'),
        ];
    }
}
