<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class QzCertificateSettings extends Page
{
    use InteractsWithForms;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Certificados QZ';
    protected static ?string $title = 'Configuração de Certificados QZ';
    protected static ?string $slug = 'qz-certificates';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.qz-certificate-settings';

    public ?array $data = [];



    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Certificados QZ Tray')
                    ->description('Configure os certificados necessários para impressão silenciosa usando QZ Tray.')
                    ->schema([
                        FileUpload::make('private_key')
                            ->label('Chave Privada (private-key.pem)')
                            ->helperText('Arquivo no formato PKCS#8 com chave de 2048-bit')
                            ->acceptedFileTypes(['.pem'])
                            ->directory('certificates')
                            ->visibility('private')
                            ->preserveFilenames()
                            ->maxSize(512),

                        FileUpload::make('digital_certificate')
                            ->label('Certificado Digital (digital-certificate.txt)')
                            ->helperText('Arquivo de certificado no formato x509')
                            ->acceptedFileTypes(['.txt'])
                            ->directory('certificates')
                            ->visibility('private')
                            ->preserveFilenames()
                            ->maxSize(512),

                        TextInput::make('pfx_password')
                            ->label('Senha do Certificado PFX')
                            ->helperText('Necessário apenas se estiver usando certificado no formato PFX')
                            ->password()
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Salvar')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // TODO: Implementar o salvamento dos certificados
        
        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Certificados salvos')
            ->body('Os certificados foram salvos com sucesso.')
            ->send();
    }
} 