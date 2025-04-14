<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Usuário';
    
    protected static ?string $pluralModelLabel = 'Usuários';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do Usuário')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create'),
                            
                        Select::make('roles')
                            ->label('Papéis')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload(),
                            
                        Toggle::make('is_active')
                            ->label('Ativo')
                            ->helperText('Desative para impedir o acesso do usuário ao sistema')
                            ->default(true)
                            ->dehydrated(function (bool $state, string $context, ?User $record, Forms\Set $set) {
                                // Se está editando o próprio usuário e tentando desativar
                                if (
                                    $context === 'edit' && 
                                    $record && 
                                    $record->id === \Illuminate\Support\Facades\Auth::id() && 
                                    $record->is_active && 
                                    !$state
                                ) {
                                    // Impede a desativação e mantém como ativo
                                    $set('is_active', true);
                                    
                                    // Notifica o usuário
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Operação não permitida')
                                        ->body("Não é possível desativar sua própria conta de usuário.")
                                        ->persistent()
                                        ->send();
                                        
                                    return false;
                                }
                                
                                return true;
                            }),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('roles.name')
                    ->label('Papéis')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (User $record): string => $record->is_active ? 'Desativar' : 'Ativar')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->is_active ? 'Desativar Usuário' : 'Ativar Usuário')
                    ->modalDescription(fn (User $record): string => 
                        $record->is_active 
                            ? "Deseja realmente desativar o usuário \"{$record->name}\"? Ele não poderá mais acessar o sistema." 
                            : "Deseja realmente ativar o usuário \"{$record->name}\"? Ele poderá acessar o sistema novamente."
                    )
                    ->modalSubmitActionLabel('Confirmar')
                    ->visible(fn (User $record): bool => $record->id !== \Illuminate\Support\Facades\Auth::id() || !$record->is_active)
                    ->action(function (User $record, Tables\Actions\Action $action): void {
                        // Impede auto-desativação
                        if ($record->id === \Illuminate\Support\Facades\Auth::id() && $record->is_active) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Operação não permitida')
                                ->body("Não é possível desativar sua própria conta de usuário.")
                                ->persistent()
                                ->send();
                                
                            return;
                        }
                        
                        $record->update(['is_active' => !$record->is_active]);
                        
                        $status = $record->is_active ? 'ativado' : 'desativado';
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Usuário atualizado')
                            ->body("O usuário \"{$record->name}\" foi {$status} com sucesso.")
                            ->send();
                    }),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, User $record) {
                        if ($record->id === \Illuminate\Support\Facades\Auth::id()) {
                            
                            \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Operação não permitida')
                            ->body("Não é possível excluir sua própria conta de usuário.")
                            ->persistent()
                            ->send();
                            
                            // Verifica se o usuário está tentando excluir a própria conta
                            $action->cancel();
                            return;
                        }
                        
                        // Verifica ocorrências relacionadas
                        if ($record->hasRelatedOccurrences()) {
                            
                            \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Exclusão não permitida')
                            ->body("Não é possível excluir o usuário '{$record->name}' pois existem ocorrências que foram criadas ou modificadas por ele.")
                            ->persistent()
                            ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, Collection $records) {
                            // Verificando se o próprio usuário está na lista de registros
                            $currentUserId = \Illuminate\Support\Facades\Auth::id();
                            if ($records->contains(fn (User $record) => $record->id === $currentUserId)) {
                                // Remover o usuário atual dos registros
                                $records = $records->filter(fn (User $record) => $record->id !== $currentUserId);
                                
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Operação não permitida')
                                    ->body("Não é possível excluir sua própria conta de usuário.")
                                    ->persistent()
                                    ->send();
                                    
                                // Atualiza os registros para excluir
                                $action->records($records);
                                
                                // Se não sobrou nenhum registro, cancela a operação
                                if ($records->isEmpty()) {
                                    $action->cancel();
                                    return;
                                }
                            }
                        
                            // Verificando se algum usuário selecionado tem ocorrências
                            $blockedUsers = $records->filter(fn (User $record) => $record->hasRelatedOccurrences());
                            
                            if ($blockedUsers->isNotEmpty()) {
                                // Se apenas um usuário tem ocorrências
                                if ($blockedUsers->count() === 1) {
                                    $user = $blockedUsers->first();
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Exclusão não permitida')
                                        ->body("Não é possível excluir o usuário '{$user->name}' pois existem ocorrências que foram criadas ou modificadas por ele.")
                                        ->persistent()
                                        ->send();
                                } else {
                                    // Se múltiplos usuários têm ocorrências
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Exclusão não permitida')
                                        ->body("Alguns dos usuários selecionados não podem ser excluídos pois existem ocorrências que foram criadas ou modificadas por eles.")
                                        ->persistent()
                                        ->send();
                                }
                                
                                // Remove os usuários bloqueados da seleção
                                $action->records($records->diff($blockedUsers));
                                
                                // Se todos estiverem bloqueados, cancela a ação
                                if ($records->count() === $blockedUsers->count()) {
                                    $action->cancel();
                                }
                            }
                        }),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
