<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QZCertificateResource\Pages;
use App\Models\QZCertificate;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class QZCertificateResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = QZCertificate::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Certificados QZ';
    protected static ?string $modelLabel = 'Certificado QZ';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('private_key')
                    ->label('Chave Privada (PKCS#8)')
                    ->helperText('Cole aqui o conteúdo do arquivo private-key.pem')
                    ->placeholder('-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEF...
-----END PRIVATE KEY-----')
                    ->required()
                    ->columnSpanFull(),

                Textarea::make('digital_certificate')
                    ->label('Certificado Digital (x509)')
                    ->helperText('Cole aqui o conteúdo do certificado digital')
                    ->placeholder('-----BEGIN CERTIFICATE-----
MIIDpTCCAo2gAwIBAgIJAOYfYfw7NCg...
-----END CERTIFICATE-----')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('pfx_password')
                    ->label('Senha do Certificado PFX')
                    ->helperText('Necessário apenas se estiver usando certificado no formato PFX')
                    ->password()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('private_key_path_full')
                    ->label('Caminho da Chave Privada')
                    ->copyable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('digital_certificate_path_full')
                    ->label('Caminho do Certificado')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQZCertificates::route('/'),
            'create' => Pages\CreateQZCertificate::route('/create'),
            'edit' => Pages\EditQZCertificate::route('/{record}/edit'),
        ];
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }
} 