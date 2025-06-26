<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class Profile extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Meu Perfil';
    protected static ?string $title = 'Meu Perfil';
    protected static ?string $slug = 'profile';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.profile';
    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Usuário não autenticado');
        }
        
        $this->data = [
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informações Pessoais')
                    ->description('Atualize suas informações pessoais.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'email', ignorable: fn() => Auth::user()),
                    ])->columns(2),

                Section::make('Atualizar Senha')
                    ->description('Certifique-se de que sua conta esteja usando uma senha longa e aleatória para se manter segura.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Senha Atual')
                            ->password()
                            ->dehydrated(false)
                            ->rules(['required_with:new_password'])
                            ->currentPassword()
                            ->autocomplete('off'),

                        TextInput::make('new_password')
                            ->label('Nova Senha')
                            ->password()
                            ->rules([
                                'nullable',
                                'confirmed',
                                Password::defaults()
                            ])
                            ->autocomplete('new-password'),

                        TextInput::make('new_password_confirmation')
                            ->label('Confirmar Nova Senha')
                            ->password()
                            ->rules([
                                'nullable',
                                'required_with:new_password',
                            ])
                            ->autocomplete('new-password'),
                    ])->columns(2),
            ])
            ->columns(4)
            ->statePath('data');
    }

    public function save()
    {
        $data = $this->data;

        /** @var User $user */
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Usuário não autenticado');
        }

        // Verificar se há alteração de senha
        if (isset($data['new_password']) && $data['new_password']) {
            // Verificar se a senha atual foi fornecida e está correta
            if (!isset($data['current_password']) || !$data['current_password']) {
                Notification::make()
                    ->title('Senha atual obrigatória')
                    ->danger()
                    ->body('Você deve fornecer sua senha atual para alterar a senha.')
                    ->send();
                return;
            }

            // Verificar se a senha atual está correta
            if (!Hash::check($data['current_password'], $user->password)) {
                Notification::make()
                    ->title('Senha atual incorreta')
                    ->danger()
                    ->body('A senha atual fornecida está incorreta.')
                    ->send();
                return;
            }

            // Verificar se a confirmação da nova senha foi fornecida
            if (!isset($data['new_password_confirmation']) || !$data['new_password_confirmation']) {
                Notification::make()
                    ->title('Confirmação de senha obrigatória')
                    ->danger()
                    ->body('Você deve confirmar sua nova senha.')
                    ->send();
                return;
            }

            // Verificar se a nova senha e a confirmação são iguais
            if ($data['new_password'] !== $data['new_password_confirmation']) {
                Notification::make()
                    ->title('Senhas não coincidem')
                    ->danger()
                    ->body('A nova senha e a confirmação da nova senha não são iguais.')
                    ->send();
                return;
            }

            // Verificar se já há dados pendentes (segunda vez clicando)
            $pendingData = session('pending_profile_update');
            
            if ($pendingData) {
                // Segunda vez clicando - executar a alteração
                session()->forget('pending_profile_update');
                $this->confirmPasswordChange();
                return;
            } else {
                // Primeira vez clicando - mostrar aviso
                session(['pending_profile_update' => $data]);
                
                Notification::make()
                    ->title('Alteração de senha detectada')
                    ->warning()
                    ->body('Sua senha será alterada e você será deslogado automaticamente. Clique em "Salvar" novamente para confirmar ou "Cancelar" para desistir.')
                    ->persistent()
                    ->send();
                
                return;
            }
        }

        // Se não há alteração de senha, salvar normalmente
        $this->updateProfile();
    }

    public function confirmPasswordChange()
    {
        // Usar diretamente os dados do formulário
        $data = $this->data;
        
        // Limpar dados pendentes da sessão
        session()->forget('pending_profile_update');
        
        // Atualizar o perfil antes de fazer logout
        /** @var User $user */
        $user = Auth::user();
        if ($user) {
            // Validar senha atual novamente antes de executar
            if (isset($data['new_password']) && $data['new_password']) {
                if (!isset($data['current_password']) || !$data['current_password']) {
                    Notification::make()
                        ->title('Senha atual obrigatória')
                        ->danger()
                        ->body('Você deve fornecer sua senha atual para alterar a senha.')
                        ->send();
                    return;
                }

                if (!Hash::check($data['current_password'], $user->password)) {
                    Notification::make()
                        ->title('Senha atual incorreta')
                        ->danger()
                        ->body('A senha atual fornecida está incorreta.')
                        ->send();
                    return;
                }

                // Verificar se a confirmação da nova senha foi fornecida
                if (!isset($data['new_password_confirmation']) || !$data['new_password_confirmation']) {
                    Notification::make()
                        ->title('Confirmação de senha obrigatória')
                        ->danger()
                        ->body('Você deve confirmar sua nova senha.')
                        ->send();
                    return;
                }

                // Verificar se a nova senha e a confirmação são iguais
                if ($data['new_password'] !== $data['new_password_confirmation']) {
                    Notification::make()
                        ->title('Senhas não coincidem')
                        ->danger()
                        ->body('A nova senha e a confirmação da nova senha não são iguais.')
                        ->send();
                    return;
                }
            }
            
            $updateData = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];
            
            // Adicionar senha se fornecida
            if (isset($data['new_password']) && $data['new_password']) {
                $updateData['password'] = Hash::make($data['new_password']);
            }
            
            $user->update($updateData);
            
            Notification::make()
                ->title('Perfil atualizado')
                ->success()
                ->send();
        }
        
        // Fazer logout de forma mais segura
        try {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        } catch (\Exception $e) {
            // Se houver erro no logout, apenas limpar a sessão
            session()->flush();
        }
        
        // Redirecionar para login
        return redirect()->route('filament.dashboard.auth.login');
    }

    public function cancelPasswordChange()
    {
        // Limpar dados pendentes da sessão
        session()->forget('pending_profile_update');
        
        // Limpar os campos de senha
        $this->data['current_password'] = '';
        $this->data['new_password'] = '';
        $this->data['new_password_confirmation'] = '';
        
        Notification::make()
            ->title('Alteração cancelada')
            ->info()
            ->body('Nenhuma alteração foi feita. Você permanecerá na página de perfil.')
            ->send();
    }

    private function updateProfile($withPassword = false)
    {
        $data = $this->data;
        /** @var User $user */
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Usuário não autenticado');
        }
        
        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if ($withPassword && isset($data['new_password']) && $data['new_password']) {
            $updateData['password'] = Hash::make($data['new_password']);
        }

        $user->update($updateData);

        Notification::make()
            ->title('Perfil atualizado')
            ->success()
            ->send();
    }

    protected function getFooterActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar')
                ->color('success')
                ->icon('heroicon-m-check')
                ->action('save'),

            Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->icon('heroicon-m-x-mark')
                ->action('cancelPasswordChange'),
        ];
    }
}
