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
    
    // Propriedades para armazenar informações dos certificados existentes
    public $privateKeyExists = false;
    public $privateKeyInfo = [];
    public $digitalCertificateExists = false;
    public $digitalCertificateInfo = [];
    public $pfxPasswordExists = false;
    public $pfxPasswordInfo = [];

    public function mount(): void
    {
        $this->form->fill();
        $this->loadExistingCertificatesInfo();
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
            
            // Recarrega as informações dos certificados
            $this->loadExistingCertificatesInfo();

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

    /**
     * Carrega informações dos certificados existentes
     * 
     * @return void
     */
    protected function loadExistingCertificatesInfo(): void
    {
        // Verificar chave privada
        $privateKeyPath = 'private/private-key.pem';
        $this->privateKeyExists = Storage::disk('local')->exists($privateKeyPath);
        
        if ($this->privateKeyExists) {
            $this->privateKeyInfo = [
                'name' => 'private-key.pem',
                'size' => $this->formatFileSize(Storage::disk('local')->size($privateKeyPath)),
                'last_modified' => $this->formatDate(Storage::disk('local')->lastModified($privateKeyPath)),
            ];
        }
        
        // Verificar certificado digital
        $digitalCertificatePath = 'digital-certificate.txt';
        $this->digitalCertificateExists = Storage::disk('public')->exists($digitalCertificatePath);
        
        if ($this->digitalCertificateExists) {
            $this->digitalCertificateInfo = [
                'name' => 'digital-certificate.txt',
                'size' => $this->formatFileSize(Storage::disk('public')->size($digitalCertificatePath)),
                'last_modified' => $this->formatDate(Storage::disk('public')->lastModified($digitalCertificatePath)),
            ];
        }
        
        // Verificar senha do PFX
        $pfxPasswordPath = 'private/pfx-password.txt';
        $this->pfxPasswordExists = Storage::disk('local')->exists($pfxPasswordPath);
        
        if ($this->pfxPasswordExists) {
            $this->pfxPasswordInfo = [
                'name' => 'pfx-password.txt',
                'last_modified' => $this->formatDate(Storage::disk('local')->lastModified($pfxPasswordPath)),
            ];
        }
    }
    
    /**
     * Formata o tamanho do arquivo para exibição amigável
     * 
     * @param int $size Tamanho em bytes
     * @return string Tamanho formatado
     */
    protected function formatFileSize(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
    
    /**
     * Formata a data para exibição amigável
     * 
     * @param int $timestamp Timestamp Unix
     * @return string Data formatada
     */
    protected function formatDate(int $timestamp): string
    {
        return date('d/m/Y H:i:s', $timestamp);
    }
} 