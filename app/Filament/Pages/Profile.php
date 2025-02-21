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

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name' => Auth::user()->name,
            'email' => Auth::user()->email,
        ]);
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
                            ->unique('users', 'email', ignorable: Auth::user()),
                    ])->columns(1),

                Section::make('Atualizar Senha')
                    ->description('Certifique-se de que sua conta esteja usando uma senha longa e aleatória para se manter segura.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Senha Atual')
                            ->password()
                            ->dehydrated(false)
                            ->required()
                            ->rules(['required_with:new_password'])
                            ->currentPassword()
                            ->autocomplete('off'),

                        TextInput::make('new_password')
                            ->label('Nova Senha')
                            ->password()
                            ->rules([
                                'confirmed',
                                Password::defaults()
                            ])
                            ->autocomplete('new-password'),

                        TextInput::make('new_password_confirmation')
                            ->label('Confirmar Nova Senha')
                            ->password()
                            ->rules([
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
        $data = $this->form->getState();

        $user = Auth::user();
        $user->name = $data['name'];
        $user->email = $data['email'];

        if (isset($data['new_password']) && $data['new_password']) {
            $user->password = Hash::make($data['new_password']);
        }

        $user->save();

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
                ->action(fn () => $this->save()),

            Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->icon('heroicon-m-x-mark')
                ->url(url()->previous()),
        ];
    }
}
