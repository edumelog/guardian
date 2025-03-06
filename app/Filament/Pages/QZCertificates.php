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
                    ->disk('local')
                    ->maxSize(512)
                    ->required(),

                FileUpload::make('digital_certificate')
                    ->label('Certificado Digital (x509)')
                    ->helperText('Selecione o arquivo do certificado digital')
                    ->disk('local')
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
        try {
            $data = $this->form->getState();

            // Remove arquivos antigos se existirem
            Storage::disk('local')->delete('private/private-key.pem');
            Storage::disk('local')->delete('private/pfx-password.txt');
            Storage::disk('public')->delete('digital-certificate.txt');

            // Processa a chave privada
            if (!empty($data['private_key'])) {
                $sourcePath = Storage::disk('local')->path($data['private_key']);
                if (!file_exists($sourcePath)) {
                    throw new \Exception('Arquivo da chave privada não encontrado');
                }
                
                // Move o arquivo para o diretório private com o nome correto
                Storage::disk('local')->put(
                    'private/private-key.pem',
                    Storage::disk('local')->get($data['private_key'])
                );
            }

            // Processa o certificado digital
            if (!empty($data['digital_certificate'])) {
                $sourcePath = Storage::disk('local')->path($data['digital_certificate']);
                if (!file_exists($sourcePath)) {
                    throw new \Exception('Arquivo do certificado digital não encontrado');
                }
                
                // Move o arquivo para o diretório public com o nome correto
                Storage::disk('public')->put(
                    'digital-certificate.txt',
                    Storage::disk('local')->get($data['digital_certificate'])
                );
            }

            // Salva a senha do PFX se fornecida
            if (!empty($data['pfx_password'])) {
                Storage::disk('local')->put('private/pfx-password.txt', $data['pfx_password']);
            }

            // Limpa os arquivos temporários
            Storage::disk('local')->delete([
                $data['private_key'] ?? '',
                $data['digital_certificate'] ?? ''
            ]);

            Notification::make()
                ->title('Certificados salvos com sucesso')
                ->success()
                ->send();

            $this->form->fill();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erro ao salvar certificados')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
} 