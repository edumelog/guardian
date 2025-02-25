<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class QZCertificates extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Certificados QZ';
    protected static ?string $title = 'Certificados QZ';
    protected static ?string $slug = 'qz-certificates';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.qz-certificates';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('private_key')
                    ->label('Chave Privada (PKCS#8)')
                    ->helperText('Selecione o arquivo private-key.pem')
                    ->disk('private')
                    ->directory('temp')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/x-pem-file', 'text/plain'])
                    ->maxSize(512)
                    ->required(),

                FileUpload::make('digital_certificate')
                    ->label('Certificado Digital (x509)')
                    ->helperText('Selecione o arquivo do certificado digital')
                    ->disk('private')
                    ->directory('temp')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/x-x509-ca-cert', 'application/x-pem-file', 'text/plain'])
                    ->maxSize(512)
                    ->required(),

                TextInput::make('pfx_password')
                    ->label('Senha do Certificado PFX')
                    ->helperText('Necessário apenas se estiver usando certificado no formato PFX')
                    ->password()
                    ->maxLength(255),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        // Remove arquivos antigos se existirem
        Storage::disk('private')->delete([
            'private-key.pem',
            'digital-certificate.txt',
            'pfx-password.txt'
        ]);

        // Processa a chave privada
        if (!empty($data['private_key'])) {
            // Move e renomeia o arquivo
            Storage::disk('private')->move(
                'temp/' . $data['private_key'],
                'private-key.pem'
            );
        }

        // Processa o certificado digital
        if (!empty($data['digital_certificate'])) {
            // Move e renomeia o arquivo
            Storage::disk('private')->move(
                'temp/' . $data['digital_certificate'],
                'digital-certificate.txt'
            );
        }

        // Salva a senha do PFX se fornecida
        if (!empty($data['pfx_password'])) {
            Storage::disk('private')->put('pfx-password.txt', $data['pfx_password']);
        }

        // Limpa arquivos temporários que possam ter sobrado
        Storage::disk('private')->deleteDirectory('temp');

        Notification::make()
            ->title('Certificados salvos com sucesso')
            ->success()
            ->send();

        $this->form->fill();
    }
} 