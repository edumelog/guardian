<?php

namespace App\Filament\Resources\VisitorResource\Pages;

use App\Filament\Resources\VisitorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use App\Filament\Forms\Components\WebcamCapture;
use App\Filament\Forms\Components\DocumentPhotoCapture;
use Filament\Forms\Get;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\View;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Grid;
use Filament\Support\RawJs;
use App\Models\Visitor;

class CreateVisitor extends CreateRecord
{
    protected static string $resource = VisitorResource::class;

    public bool $showAllFields = false;

    public function mount(): void
    {
        parent::mount();
        
        // Verifica se há parâmetros na URL para preencher o formulário
        $doc = request()->query('doc');
        $docTypeId = request()->query('doc_type_id');
        
        if ($doc && $docTypeId) {
            // Preenche o formulário com os dados da URL
            $this->form->fill([
                'doc' => $doc,
                'doc_type_id' => $docTypeId,
            ]);
            
            // Chama o método de busca para preencher os demais campos
            $this->searchVisitor();
            
            // Exibe uma notificação informativa
            Notification::make()
                ->info()
                ->title('Registrar Nova Entrada')
                ->body('Selecione o destino e clique em "Imprimir Credencial e Salvar" para registrar a entrada do visitante.')
                ->send();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informações do Visitante')
                    ->schema([
                        Select::make('doc_type_id')
                            ->label('Tipo de Documento')
                            ->relationship('docType', 'type')
                            ->required()
                            ->default(function () {
                                return \App\Models\DocType::where('is_default', true)->first()?->id;
                            })
                            ->live()
                            ->dehydrated(true)
                            ->disabled(fn (Get $get): bool => $this->showAllFields),
                            
                        TextInput::make('doc')
                            ->label('Número do Documento')
                            ->required()
                            ->maxLength(255)
                            ->numeric()
                            ->inputMode('numeric')
                            ->step(1)
                            ->dehydrated(true)
                            ->disabled(fn (Get $get): bool => $this->showAllFields)
                            ->extraInputAttributes([
                                'x-data' => '{ 
                                    isCpf: false,
                                    docTypeId: null,
                                    docValue: null,
                                    errorMessage: "",
                                    cpfValid: true,
                                    
                                    // Função para validar o CPF
                                    validateCpf(cpf) {
                                        if (!cpf) return true;
                                        
                                        // Remove caracteres não numéricos
                                        cpf = cpf.replace(/[^\d]/g, "");
                                        
                                        // Verifica se tem 11 dígitos
                                        if (cpf.length !== 11) return false;
                                        
                                        // Verifica se todos os dígitos são iguais
                                        if (/^(\d)\1+$/.test(cpf)) return false;
                                        
                                        // Validação dos dígitos verificadores
                                        let soma = 0;
                                        let resto;
                                        
                                        // Primeiro dígito verificador
                                        for (let i = 1; i <= 9; i++) {
                                            soma = soma + parseInt(cpf.substring(i-1, i)) * (11 - i);
                                        }
                                        resto = (soma * 10) % 11;
                                        if ((resto === 10) || (resto === 11)) resto = 0;
                                        if (resto !== parseInt(cpf.substring(9, 10))) return false;
                                        
                                        // Segundo dígito verificador
                                        soma = 0;
                                        for (let i = 1; i <= 10; i++) {
                                            soma = soma + parseInt(cpf.substring(i-1, i)) * (12 - i);
                                        }
                                        resto = (soma * 10) % 11;
                                        if ((resto === 10) || (resto === 11)) resto = 0;
                                        if (resto !== parseInt(cpf.substring(10, 11))) return false;
                                        
                                        return true;
                                    },
                                    
                                    getDocTypeElement() {
                                        return document.querySelector(\'select[name="doc_type_id"]\');
                                    },
                                    
                                    isCpfDocument() {
                                        const cpfSelect = this.getDocTypeElement();
                                        if (!cpfSelect) return false;
                                        
                                        const selectedIndex = cpfSelect.selectedIndex;
                                        if (selectedIndex === -1) return false;
                                        
                                        const selectedOption = cpfSelect.options[selectedIndex];
                                        return selectedOption && selectedOption.text.toUpperCase().includes("CPF");
                                    },
                                    
                                    checkCpf() {
                                        // Verifica se o tipo de documento é CPF
                                        const cpfSelect = this.getDocTypeElement();
                                        if (!cpfSelect) return; // Se o elemento não existe, não faz nada
                                        
                                        this.docTypeId = cpfSelect.value;
                                        this.isCpf = this.isCpfDocument();
                                        
                                        // Se for CPF, valida o documento
                                        if (this.isCpf && this.docValue) {
                                            this.cpfValid = this.validateCpf(this.docValue);
                                            if (!this.cpfValid) {
                                                this.errorMessage = "CPF inválido! Verifique o número informado.";
                                                // Desabilitar o botão de busca
                                                const searchBtn = document.querySelector(\'button[aria-label="Buscar visitante por documento"]\');
                                                if (searchBtn) {
                                                    searchBtn.disabled = true;
                                                    searchBtn.classList.add(\'opacity-50\', \'cursor-not-allowed\');
                                                }
                                                // Notificar o usuário sobre o CPF inválido
                                                const event = new CustomEvent("cpf-invalid", {
                                                    detail: { message: this.errorMessage }
                                                });
                                                window.dispatchEvent(event);
                                            } else {
                                                this.errorMessage = "";
                                                // Habilitar o botão de busca novamente
                                                const searchBtn = document.querySelector(\'button[aria-label="Buscar visitante por documento"]\');
                                                if (searchBtn) {
                                                    searchBtn.disabled = false;
                                                    searchBtn.classList.remove(\'opacity-50\', \'cursor-not-allowed\');
                                                }
                                            }
                                        } else {
                                            this.cpfValid = true;
                                            this.errorMessage = "";
                                            // Habilitar o botão de busca
                                            const searchBtn = document.querySelector(\'button[aria-label="Buscar visitante por documento"]\');
                                            if (searchBtn) {
                                                searchBtn.disabled = false;
                                                searchBtn.classList.remove(\'opacity-50\', \'cursor-not-allowed\');
                                            }
                                        }
                                    },
                                    
                                    init() {
                                        // Inicializa com o valor atual do campo
                                        this.docValue = this.$el.value;
                                        
                                        // Monitora alterações nos valores
                                        this.$watch("docValue", value => { 
                                            this.checkCpf();
                                        });
                                        
                                        // Verifica o tipo de documento inicial
                                        this.$nextTick(() => {
                                            const cpfSelect = this.getDocTypeElement();
                                            if (cpfSelect) {
                                                this.docTypeId = cpfSelect.value;
                                                this.isCpf = this.isCpfDocument();
                                                
                                                // Adiciona um event listener para o select
                                                cpfSelect.addEventListener("change", () => {
                                                    this.docTypeId = cpfSelect.value;
                                                    this.isCpf = this.isCpfDocument();
                                                    this.checkCpf();
                                                });
                                            }
                                        });
                                    }
                                }',
                                'x-init' => 'init()',
                                'x-on:input' => 'docValue = $event.target.value; checkCpf()',
                                'x-on:keydown.enter.prevent' => 'if (cpfValid) { $wire.call("searchVisitor") }'
                            ])
                            ->suffixAction(
                                Action::make('search')
                                    ->icon('heroicon-m-magnifying-glass')
                                    ->tooltip('Buscar visitante por documento')
                                    ->action(function () {
                                        $this->searchVisitor();
                                    })
                            ),
                            
                        Placeholder::make('cpf_error')
                            ->label('')
                            ->content(fn (): string => 'CPF inválido! Verifique o número informado.')
                            ->extraAttributes([
                                'class' => 'hidden text-danger-500 dark:text-danger-400 font-medium',
                                'x-data' => '{}',
                                'x-init' => '
                                    window.addEventListener("cpf-invalid", (event) => {
                                        $el.classList.remove("hidden");
                                        setTimeout(() => {
                                            $el.classList.add("hidden");
                                        }, 5000);
                                    });
                                '
                            ]),

                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->disabled(function (Get $get) {
                                // Se já existir um visitante com este documento, desabilita o campo
                                $doc = $get('doc');
                                $docTypeId = $get('doc_type_id');
                                if (!$doc || !$docTypeId) return false;
                                
                                return \App\Models\Visitor::where('doc', $doc)
                                    ->where('doc_type_id', $docTypeId)
                                    ->exists();
                            })
                            ->regex('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\.\-\']+$/')
                            ->extraInputAttributes([
                                'style' => 'text-transform: uppercase;',
                                'x-on:keypress' => "if (!/[A-Za-zÀ-ÖØ-öø-ÿ\s\.\-\']/.test(event.key)) { event.preventDefault(); }"
                            ])
                            ->afterStateUpdated(function (string $state, callable $set) {
                                $set('name', mb_strtoupper($state));
                            })
                            ->validationMessages([
                                'regex' => 'O nome deve conter apenas letras, espaços e caracteres especiais (. - \').',
                            ]),

                        TextInput::make('phone')
                            ->label('Telefone')
                            ->tel()
                            ->telRegex('/.*/')  // Aceita qualquer formato de telefone
                            ->mask(RawJs::make(<<<'JS'
                                '99 (99) 99-999-9999'
                            JS))
                            ->default('55 (21) ')
                            ->placeholder('55 (21) 99-999-9999')
                            ->visible(fn (Get $get): bool => $this->showAllFields),

                        Grid::make(3)
                            ->schema([
                                WebcamCapture::make('photo')
                                    ->label('Foto')
                                    ->required()
                                    ->visible(fn (Get $get): bool => $this->showAllFields),

                                DocumentPhotoCapture::make('doc_photo_front')
                                    ->label('Foto do Documento (Frente)')
                                    ->required()
                                    ->visible(fn (Get $get): bool => $this->showAllFields),

                                DocumentPhotoCapture::make('doc_photo_back')
                                    ->label('Foto do Documento (Verso)')
                                    ->required()
                                    ->visible(fn (Get $get): bool => $this->showAllFields),
                            ]),

                        Select::make('destination_id')
                            ->label('Destino')
                            ->required()
                            ->searchable()
                            ->live()
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\Destination::where('is_active', true)
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('address', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($destination) => [
                                        $destination->id => $destination->address 
                                            ? "{$destination->name} - {$destination->address}"
                                            : $destination->name
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => 
                                \App\Models\Destination::where('is_active', true)
                                    ->find($value)?->address 
                                        ? \App\Models\Destination::find($value)->name . ' - ' . \App\Models\Destination::find($value)->address
                                        : \App\Models\Destination::find($value)?->name
                            )
                            ->placeholder('Digite o nome ou endereço do destino')
                            ->columnSpanFull(),

                        Placeholder::make('destination_phone')
                            ->label('Telefone do Destino')
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->content(function ($get) {
                                $destinationId = $get('destination_id');
                                if (!$destinationId) return '-';
                                
                                $destination = \App\Models\Destination::find($destinationId);
                                if (!$destination || !$destination->is_active) {
                                    return '-';
                                }
                                return $destination->phone ?: 'Não cadastrado';
                            })
                            ->columnSpanFull(),

                        Placeholder::make('visitors_count')
                            ->label('Visitantes presentes no destino:')
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->content(function ($get) {
                                $destinationId = $get('destination_id');
                                if (!$destinationId) return '-';
                                
                                $destination = \App\Models\Destination::find($destinationId);
                                if (!$destination || !$destination->is_active) {
                                    return '-';
                                }

                                $currentCount = $destination->getCurrentVisitorsCount();
                                $maxVisitors = $destination->max_visitors;

                                // Se não tem limite, mostra em preto sem destaque
                                if ($maxVisitors <= 0) {
                                    return new \Illuminate\Support\HtmlString(
                                        "<span class='text-gray-900'>{$currentCount}</span>"
                                    );
                                }

                                // Calcula a porcentagem de ocupação
                                $occupancyRate = ($currentCount / $maxVisitors) * 100;

                                // Define a cor e estilo baseado na ocupação
                                if ($currentCount >= $maxVisitors) {
                                    // Vermelho quando atingir o limite
                                    $style = 'text-red-600 dark:text-red-400';
                                    
                                    \Filament\Notifications\Notification::make()
                                        ->warning()
                                        ->title('Limite de visitantes atingido')
                                        ->body("O destino {$destination->name} atingiu o limite de {$maxVisitors} visitantes.")
                                        ->persistent()
                                        ->send();
                                } elseif ($occupancyRate >= 50 && $occupancyRate < 80) {
                                    // Laranja entre 50% e 80%
                                    $style = 'text-orange-500 dark:text-orange-400';
                                } else {
                                    // Verde abaixo de 50%
                                    $style = 'text-emerald-600 dark:text-emerald-400';
                                }

                                return new \Illuminate\Support\HtmlString(
                                    "<span class='{$style}'>{$currentCount}/{$maxVisitors}</span>"
                                );
                            })
                            ->columnSpanFull(),
                            
                        Textarea::make('other')
                            ->label('Informações Adicionais')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->columnSpanFull(),

                        Placeholder::make('current_entry')
                            ->label('Data de Entrada')
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->content(now()->format('d/m/Y H:i')),
                    ])->columns(2),

                Section::make()
                    ->schema([
                        View::make('filament.forms.components.destination-hierarchy-view')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (): bool => $this->showAllFields),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        // Se não estiver mostrando todos os campos, não mostra nenhuma ação
        if (!$this->showAllFields) {
            return [];
        }

        // Sempre mostra o botão de criar com impressão e o botão cancelar
        return [
            $this->getCreateFormAction()
                ->label('Imprimir Credencial e Salvar')
                ->color('success')
                ->icon('heroicon-o-printer')
                ->action(function () {
                    // Verifica se há visita em andamento
                    $formData = $this->form->getState();
                    
                    $visitor = \App\Models\Visitor::where('doc', $formData['doc'] ?? null)
                        ->where('doc_type_id', $formData['doc_type_id'] ?? null)
                        ->with(['docType', 'activeRestrictions'])
                        ->first();

                    if ($visitor) {
                        $lastVisit = $visitor->visitorLogs()
                            ->latest('in_date')
                            ->first();

                        if ($lastVisit && $lastVisit->out_date === null) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Visita em Andamento')
                                ->body("Este visitante já possui uma visita em andamento.")
                                ->persistent()
                                ->send();
                            return;
                        }
                    }

                    $this->create();
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url(url()->previous()),
        ];
    }

    public function searchVisitor(): void
    {
        // Se o formulário ainda não foi inicializado, retorna
        if (!$this->form) {
            return;
        }
        
        $formData = $this->form->getState();
        
        if (!isset($formData['doc']) || !isset($formData['doc_type_id'])) {
            return;
        }

        // Verifica se o tipo de documento é CPF e se o formato é válido
        $docTypeId = $formData['doc_type_id'];
        $docType = \App\Models\DocType::find($docTypeId);
        
        if ($docType && stripos($docType->type, 'CPF') !== false) {
            // Remove caracteres não numéricos
            $cpf = preg_replace('/[^0-9]/', '', $formData['doc']);
            
            // Verifica se tem 11 dígitos
            if (strlen($cpf) !== 11) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('CPF inválido')
                    ->body('O número de CPF informado não possui 11 dígitos.')
                    ->send();
                return;
            }
            
            // Verifica se todos os dígitos são iguais
            if (preg_match('/^(\d)\1+$/', $cpf)) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('CPF inválido')
                    ->body('CPF inválido. Todos os dígitos são iguais.')
                    ->send();
                return;
            }
            
            // Validação do primeiro dígito verificador
            $soma = 0;
            for ($i = 1; $i <= 9; $i++) {
                $soma += intval(substr($cpf, $i-1, 1)) * (11 - $i);
            }
            $resto = ($soma * 10) % 11;
            if (($resto === 10) || ($resto === 11)) $resto = 0;
            if ($resto !== intval(substr($cpf, 9, 1))) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('CPF inválido')
                    ->body('O número de CPF informado é inválido.')
                    ->send();
                return;
            }
            
            // Validação do segundo dígito verificador
            $soma = 0;
            for ($i = 1; $i <= 10; $i++) {
                $soma += intval(substr($cpf, $i-1, 1)) * (12 - $i);
            }
            $resto = ($soma * 10) % 11;
            if (($resto === 10) || ($resto === 11)) $resto = 0;
            if ($resto !== intval(substr($cpf, 10, 1))) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('CPF inválido')
                    ->body('O número de CPF informado é inválido.')
                    ->send();
                return;
            }
        }

        $visitor = \App\Models\Visitor::where('doc', $formData['doc'])
            ->where('doc_type_id', $formData['doc_type_id'])
            ->with(['docType', 'activeRestrictions'])
            ->first();
            
        if (!$visitor) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Visitante não encontrado')
                ->body('Nenhum visitante encontrado com este documento.')
                ->send();

            $this->showAllFields = true;
            return;
        }

        // Verifica se o visitante possui restrições ativas
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Verificando restrições para visitante', [
            'visitor_id' => $visitor->id,
            'doc' => $visitor->doc,
            'name' => $visitor->name,
        ]);

        // Verifica diretamente as restrições associadas
        $activeRestrictions = \App\Models\VisitorRestriction::where('visitor_id', $visitor->id)
            ->active()
            ->get();

        \Illuminate\Support\Facades\Log::info('CreateVisitor: Resultado da consulta direta de restrições', [
            'visitor_id' => $visitor->id,
            'count' => $activeRestrictions->count(),
            'restrições' => $activeRestrictions->toArray(),
        ]);

        if ($visitor->hasActiveRestrictions() || $activeRestrictions->count() > 0) {
            // Obtém a restrição mais crítica
            $restriction = $visitor->getMostCriticalRestrictionAttribute();
            
            if (!$restriction && $activeRestrictions->count() > 0) {
                $restriction = $activeRestrictions->first();
            }
            
            \Illuminate\Support\Facades\Log::info('CreateVisitor: Restrição encontrada', [
                'restriction' => $restriction ? $restriction->toArray() : null,
            ]);
            
            if ($restriction) {
                // Formata a data de expiração
                $expiraEm = $restriction->expires_at 
                    ? $restriction->expires_at->format('d M Y') 
                    : 'Nunca';
                
                // Mostra um alerta usando JavaScript
                $this->js("
                    alert('⚠️ ALERTA: Visitante com restrição ativa!\\n\\nVisitante: {$visitor->name}\\nMotivo: {$restriction->reason}');
                    
                    // Destaca o formulário para chamar atenção
                    setTimeout(() => {
                        const form = document.querySelector('form');
                        if (form) {
                            form.style.border = '2px solid #ef4444';
                            form.style.boxShadow = '0 0 10px rgba(239, 68, 68, 0.5)';
                        }
                    }, 500);
                ");
                
                // Usa uma notificação do Filament
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('ALERTA: Restrição Detectada')
                    ->body("O visitante {$visitor->name} possui uma restrição ativa: {$restriction->reason}")
                    ->persistent()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('ver_detalhes')
                            ->label('Ver Todas Restrições')
                            ->url(route('filament.dashboard.resources.visitor-restrictions.index'))
                            ->color('danger')
                    ])
                    ->send();
            }
        }

        // Verifica se há uma visita em andamento
        $lastVisit = $visitor->visitorLogs()
            ->latest('in_date')
            ->first();

        if ($lastVisit && $lastVisit->out_date === null) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Visita em Andamento')
                ->body("Este visitante já possui uma visita em andamento no local: {$lastVisit->destination->name}")
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('Ver Detalhes')
                        ->url(route('filament.dashboard.resources.visitors.edit', $visitor))
                        ->button(),
                ])
                ->send();
            return;
        }

        // Se não tem visita em andamento, preenche os dados
        $this->form->fill([
            'doc' => $visitor->doc,
            'doc_type_id' => $visitor->doc_type_id,
            'name' => $visitor->name,
            'photo' => $visitor->photo,
            'doc_photo_front' => $visitor->doc_photo_front,
            'doc_photo_back' => $visitor->doc_photo_back,
            'other' => $visitor->other,
            'phone' => $visitor->phone,
        ]);

        // Dispara eventos para atualizar os previews das fotos
        $photoData = [
            'photo' => $visitor->photo ? route('visitor.photo', ['filename' => $visitor->photo]) : null,
            'doc_photo_front' => null,
            'doc_photo_back' => null,
        ];
        
        // Verifica se os nomes dos arquivos das fotos dos documentos são consistentes com o lado
        if ($visitor->doc_photo_front) {
            // Verifica se o nome do arquivo contém '_front.'
            if (strpos($visitor->doc_photo_front, '_front.') !== false) {
                $photoData['doc_photo_front'] = route('visitor.photo', ['filename' => $visitor->doc_photo_front]);
            } else {
                // Extrai as partes do nome do arquivo
                $parts = explode('_', pathinfo($visitor->doc_photo_front, PATHINFO_FILENAME));
                if (count($parts) >= 2) {
                    // Reconstrói o nome do arquivo com o lado correto
                    $correctFilename = $parts[0] . '_' . $parts[1] . '_front.jpg';
                    \Illuminate\Support\Facades\Log::warning("CreateVisitor: Nome do arquivo da foto frontal inconsistente", [
                        'original' => $visitor->doc_photo_front,
                        'corrected' => $correctFilename
                    ]);
                    $photoData['doc_photo_front'] = route('visitor.photo', ['filename' => $correctFilename]);
                } else {
                    $photoData['doc_photo_front'] = route('visitor.photo', ['filename' => $visitor->doc_photo_front]);
                }
            }
        }
        
        if ($visitor->doc_photo_back) {
            // Verifica se o nome do arquivo contém '_back.'
            if (strpos($visitor->doc_photo_back, '_back.') !== false) {
                $photoData['doc_photo_back'] = route('visitor.photo', ['filename' => $visitor->doc_photo_back]);
            } else {
                // Extrai as partes do nome do arquivo
                $parts = explode('_', pathinfo($visitor->doc_photo_back, PATHINFO_FILENAME));
                if (count($parts) >= 2) {
                    // Reconstrói o nome do arquivo com o lado correto
                    $correctFilename = $parts[0] . '_' . $parts[1] . '_back.jpg';
                    \Illuminate\Support\Facades\Log::warning("CreateVisitor: Nome do arquivo da foto traseira inconsistente", [
                        'original' => $visitor->doc_photo_back,
                        'corrected' => $correctFilename
                    ]);
                    $photoData['doc_photo_back'] = route('visitor.photo', ['filename' => $correctFilename]);
                } else {
                    $photoData['doc_photo_back'] = route('visitor.photo', ['filename' => $visitor->doc_photo_back]);
                }
            }
        }
        
        // Log para depuração
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Dados das fotos', [
            'photoData' => $photoData
        ]);
        
        $this->dispatch('photo-found', photoData: $photoData);

        $this->showAllFields = true;

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Visitante encontrado')
            ->body('Os dados do visitante foram preenchidos automaticamente.')
            ->send();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Verifica se o visitante já existe
        $formData = $this->form->getRawState();
        
        // Log para depuração
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Dados do formulário', [
            'formData' => $formData,
            'data' => $data
        ]);
        
        $doc = $formData['doc'] ?? null;
        $docTypeId = $formData['doc_type_id'] ?? null;
        $destinationId = $formData['destination_id'] ?? null;

        if (!$doc || !$docTypeId) {
            $this->halt();
            return $data;
        }

        // Verifica se o destino está ativo
        $destination = \App\Models\Destination::find($destinationId);
        if (!$destination || !$destination->is_active) {
            Notification::make()
                ->danger()
                ->title('Destino inválido')
                ->body('O destino selecionado está inativo ou não existe.')
                ->send();
            $this->halt();
            return $data;
        }

        // Verifica o limite de visitantes
        if ($destination->max_visitors > 0) {
            $currentCount = $destination->getCurrentVisitorsCount();
            if ($currentCount >= $destination->max_visitors) {
                Notification::make()
                    ->danger()
                    ->title('Limite de visitantes atingido')
                    ->body("O destino {$destination->name} atingiu o limite de {$destination->max_visitors} visitantes.")
                    ->persistent()
                    ->send();
                $this->halt();
                return $data;
            }
        }

        $visitor = \App\Models\Visitor::where('doc', $doc)
            ->where('doc_type_id', $docTypeId)
            ->first();

        if ($visitor) {
            // Se o visitante existe, atualiza apenas as informações que devem ser atualizáveis
            $visitor->update([
                'other' => $data['other'] ?? null,
                'phone' => $data['phone'] ?? null
                // O nome não é incluído aqui para garantir que não seja alterado
            ]);

            $visitor->visitorLogs()->create([
                'destination_id' => $data['destination_id'],
                'in_date' => now(),
                'operator_id' => Auth::id(),
            ]);

            // Notifica o usuário
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Nova visita registrada')
                ->body('Uma nova visita foi registrada para o visitante existente.')
                ->send();

            // Redireciona para a página de edição do visitante
            $this->redirect($this->getResource()::getUrl('edit', ['record' => $visitor]));
            
            $this->halt();
        }

        // Se o visitante não existe, inclui os dados do documento
        $data['doc'] = $doc;
        $data['doc_type_id'] = $docTypeId;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Verifica se o destino está ativo
        $formData = $this->form->getRawState();
        $destination = \App\Models\Destination::find($formData['destination_id']);
        
        if (!$destination || !$destination->is_active) {
            Notification::make()
                ->danger()
                ->title('Destino inválido')
                ->body('O destino selecionado está inativo ou não existe.')
                ->send();
            return;
        }

        // Cria o log de visita APENAS se for um novo visitante
        // (visitantes existentes já têm o log criado em mutateFormDataBeforeCreate)
        if (!$this->record->visitorLogs()->where('in_date', now())->exists()) {
            $this->record->visitorLogs()->create([
                'destination_id' => $formData['destination_id'],
                'in_date' => now(),
                'operator_id' => Auth::id(),
            ]);
        }

        // Redireciona para a página de edição
        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
    }
}
