<?php

namespace App\Filament\Resources\VisitorResource\Pages;

use Filament\Actions;
use App\Models\Visitor;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Support\RawJs;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\View;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Models\CommonVisitorRestriction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\VisitorResource;
use Filament\Forms\Components\Actions\Action;
use App\Filament\Forms\Components\WebcamCapture;
use App\Filament\Forms\Components\DocumentPhotoCapture;
use App\Models\AutomaticOccurrence;
use App\Models\Destination;
use App\Models\DocType;
use App\Models\Occurrence;
use App\Models\VisitorLog;
use App\Services\PredictiveRestrictionService;
use Filament\Forms\Components\Component;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Services\OccurrenceService;

class CreateVisitor extends CreateRecord
{
    protected static string $resource = VisitorResource::class;

    // protected ?string $maxContentWidth = MaxWidth::Full->value;

    public bool $showAllFields = false;
    public $visitorRestrictions = []; // Array para armazenar todas as restrições aplicáveis
    public $activeRestriction = null; // Armazena a restrição ativa principal
    public $authorization_granted = false; // Nova propriedade para controlar autorização

    public function mount(): void
    {
        parent::mount();
        
        // Reinicia as propriedades
        $this->visitorRestrictions = [];
        $this->activeRestriction = null;
        $this->authorization_granted = false;
        
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
                        // Campo de ALERTA de restrição que aparece apenas quando há restrições comuns != NENHUMA
                        Placeholder::make('restriction_alert')
                            ->label(function () {
                                if (empty($this->visitorRestrictions) || $this->activeRestriction->severity_level === 'none') {
                                    return null;
                                }

                                // Determina a maior severidade entre todas as restrições
                                $maxSeverity = 'none';
                                foreach ($this->visitorRestrictions as $restriction) {
                                    if ($restriction->severity_level === 'high') {
                                        $maxSeverity = 'high';
                                        break; // Alta severidade é a máxima, podemos parar aqui
                                    } elseif ($restriction->severity_level === 'medium' && $maxSeverity !== 'high') {
                                        $maxSeverity = 'medium';
                                    } elseif ($restriction->severity_level === 'low' && $maxSeverity !== 'high' && $maxSeverity !== 'medium') {
                                        $maxSeverity = 'low';
                                    }elseif ($restriction->severity_level === 'none') {
                                        $maxSeverity = 'none';
                                    }
                                }
                                
                                $severityText = match ($maxSeverity) {
                                    'none' => 'Nenhuma (Apenas Informativa)',
                                    'low' => 'Baixa',
                                    'medium' => 'Média',
                                    'high' => 'Alta',
                                    default => 'Nenhuma (Apenas Informativa)',
                                };
                                
                                $colorClass = match ($maxSeverity) {
                                    'none' => 'text-gray-400 dark:text-gray-400 font-bold',
                                    'low' => 'text-green-600 dark:text-success-400 font-bold',
                                    'medium' => 'text-amber-600 dark:text-warning-400 font-bold',
                                    'high' => 'text-red-600 dark:text-danger-400 font-bold',
                                    default => 'text-gray-400 dark:text-gray-400 font-bold',
                                };
                                
                                // Define o texto do alerta incluindo o número de restrições
                                $count = count($this->visitorRestrictions);
                                $alertText = $count > 1 
                                    ? "ALERTA: {$count} Restrições Encontradas (Severidade Máxima: {$severityText})"
                                    : "ALERTA: Restrição de Severidade {$severityText}";
                                
                                // Define a cor do label baseada na severidade
                                return new \Illuminate\Support\HtmlString(
                                    "<span class='{$colorClass}'>{$alertText}</span>"
                                );
                            })
                            ->content(function () {
                                if (empty($this->visitorRestrictions) || $this->activeRestriction->severity_level === 'none') {
                                    return null;
                                }
                                
                                // Constrói o HTML para exibir todas as restrições
                                $html = "";
                                
                                foreach ($this->visitorRestrictions as $index => $restriction) {
                                    $severityClass = match ($restriction->severity_level) {
                                        'none' => 'text-gray-400 dark:text-gray-400',
                                    'low' => 'text-green-600 dark:text-success-400',
                                    'medium' => 'text-amber-600 dark:text-amber-400',
                                    'high' => 'text-red-600 dark:text-danger-400',
                                        default => 'text-gray-400 dark:text-gray-400',
                                    };
                                    
                                    $borderClass = match ($restriction->severity_level) {
                                        'none' => 'border-gray-400 dark:border-gray-400',
                                        'low' => 'border-green-600 dark:border-success-400',
                                        'medium' => 'border-amber-600 dark:border-amber-400',
                                        'high' => 'border-red-600 dark:border-danger-400',
                                        default => 'border-gray-400 dark:border-gray-400',
                                };
                                
                                $expirationInfo = '';
                                    if (isset($restriction->expires_at) && $restriction->expires_at) {
                                        $expirationDate = is_string($restriction->expires_at) 
                                            ? date('d/m/Y', strtotime($restriction->expires_at))
                                            : $restriction->expires_at->format('d/m/Y');
                                        $expirationInfo = "<span class='font-medium'>Expira em:</span> " . $expirationDate;
                                    } else {
                                        $expirationInfo = "<span class='font-medium'>Expiração:</span> Sem data definida";
                                    }
                                    
                                    $restrictionType = isset($restriction->restriction_type) 
                                        ? $restriction->restriction_type 
                                        : 'Restrição Comum';
                                    
                                    $html .= "
                                    <div class='p-4 rounded-lg border-2 {$borderClass} mb-3'>
                                        <div class='flex justify-between items-start'>
                                            <h3 class='font-bold {$severityClass}'>Restrição #" . ($index + 1) . " ({$restrictionType})</h3>
                                            <span class='font-medium {$severityClass}'>" . match ($restriction->severity_level) {
                                                'none' => 'Severidade: Nenhuma (Apenas Informativa)',
                                                'low' => 'Severidade: Baixa',
                                                'medium' => 'Severidade: Média',
                                                'high' => 'Severidade: Alta',
                                                default => 'Severidade: Desconhecida',
                                            } . "</span>
                                        </div>
                                        <p class='my-2 {$severityClass}'>{$restriction->reason}</p>
                                        <div class='flex flex-col gap-2 text-sm'>
                                            <p>{$expirationInfo}</p>" .
                                            (isset($restriction->match_reason) && $restriction->match_reason 
                                                ? "<p><span class='font-medium'>Correspondência:</span> {$restriction->match_reason}</p>" 
                                                : "") . "
                                        </div>
                                    </div>";
                                }
                                
                                // Se foram encontradas múltiplas restrições, adiciona uma mensagem explicativa
                                if (count($this->visitorRestrictions) > 1) {
                                    $html = "<div class='mb-3 font-medium'>
                                        <p>Foram encontradas " . count($this->visitorRestrictions) . " restrições para este visitante. 
                                        Todas as restrições devem ser autorizadas antes de prosseguir.</p>
                                    </div>" . $html;
                                }
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->visible(fn () => !empty($this->visitorRestrictions) && $this->activeRestriction->severity_level !== 'none')
                            ->columnSpanFull(),

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

                        Grid::make(2)
                            ->schema([
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
                                    ])
                                    ->columnSpan(1),

                                TextInput::make('phone')
                                    ->label('Telefone')
                                    ->tel()
                                    ->telRegex('/.*/')  // Aceita qualquer formato de telefone
                                    ->mask(RawJs::make(<<<'JS'
                                        '99 (99) 99-999-9999'
                                    JS))
                                    ->default('55 (21) ')
                                    ->placeholder('55 (21) 99-999-9999')
                                    ->visible(fn (Get $get): bool => $this->showAllFields)
                                    ->columnSpan(1),
                            ]),

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

                                // Se não tem limite, mostra em azul
                                if ($maxVisitors <= 0) {
                                    return new \Illuminate\Support\HtmlString(
                                        "<span class='text-blue-600 dark:text-blue-400'>{$currentCount}</span>"
                                    );
                                }

                                // Calcula a porcentagem de ocupação
                                $occupancyRate = ($currentCount / $maxVisitors) * 100;

                                // Define a cor e estilo baseado na ocupação
                                if ($occupancyRate >= 100) {
                                    // Vermelho para 100% ou mais
                                    $style = 'text-red-600 dark:text-red-400';
                                    
                                    \Filament\Notifications\Notification::make()
                                        ->warning()
                                        ->title('Limite de visitantes atingido')
                                        ->body("O destino {$destination->name} atingiu o limite de {$maxVisitors} visitantes.")
                                        // ->persistent()
                                        ->send();
                                } elseif ($occupancyRate >= 75) {
                                    // Laranja para 75% ou mais
                                    $style = 'text-orange-500 dark:text-orange-400';
                                } elseif ($occupancyRate >= 50) {
                                    // Amarelo para 50% ou mais
                                    $style = 'text-yellow-500 dark:text-yellow-400';
                                } else {
                                    // Verde para < 50%
                                    $style = 'text-green-600 dark:text-green-400';
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
                    
                // Mensagem de aviso sobre restrição no rodapé
                Placeholder::make('restriction_warning')
                // Esta é a msg que aparece no rodapé quando um visitante tem uma restrição
                    ->label('Visitante com Restrição')
                    ->content(function() {
                        if (!$this->activeRestriction) {
                            return null;
                        }
                        
                        // Se já foi autorizado, mostra mensagem de autorização concedida
                        if ($this->authorization_granted) {
                            return new \Illuminate\Support\HtmlString(
                                "<div>
                                    <p class='text-green-600 dark:text-success-400 font-medium text-sm'>Autorização concedida. Pode prosseguir com o registro.</p>
                                </div>"
                            );
                        }
                        
                        $colorClass = match ($this->activeRestriction->severity_level) {
                            'none' => 'text-gray-400 dark:text-gray-400',
                            'low' => 'text-green-600 dark:text-success-400',
                            'medium' => 'text-amber-600 dark:text-amber-400',
                            'high' => 'text-red-600 dark:text-danger-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                        
                        return new \Illuminate\Support\HtmlString(
                            "<div>
                                <p class='{$colorClass} font-medium text-sm'>Necessita de autorização para prosseguir.</p>
                            </div>"
                        );
                    })
                    // Visibilidade da mensagem de restrição: oculta se a restrição for nenhuma, se não houver restrição ou se já foi autorizada
                    ->visible(fn() => $this->activeRestriction !== null && $this->activeRestriction->severity_level !== 'none' && !$this->authorization_granted)
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        // Sempre mostra o botão de criar com impressão e o botão cancelar
        // O botão de criar com impressão só aparece habillitado se não houver restrição, se a restrição for do tipo none ou se já foi autorizada
        return [
            $this->getCreateFormAction()
                ->label('Salvar dados do Visitante')
                ->color('success')
                ->icon('heroicon-o-printer')
                ->visible(fn () => true) // Sempre visível
                ->disabled(fn() => ($this->activeRestriction !== null && $this->activeRestriction->severity_level !== 'none' && !$this->authorization_granted) || !$this->showAllFields)
                ->action(function () {
                    // Verifica se há visita em andamento para visitantes existentes
                    $formData = $this->form->getState();
                    
                    $visitor = \App\Models\Visitor::where('doc', $formData['doc'] ?? null)
                        ->where('doc_type_id', $formData['doc_type_id'] ?? null)
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
                                ->send();
                            return;
                        }
                    }

                    // As verificações de restrições (comuns e preditivas) serão feitas no mutateFormDataBeforeCreate
                    // que será chamado durante a execução do método create()
                    $this->create();
                }),

            Actions\Action::make('authorize_restriction')
                ->label(fn() => count($this->visitorRestrictions) > 1 ? 'Liberar Restrições' : 'Liberar Restrição')
                ->color('warning')
                ->icon('heroicon-o-key')
                ->visible(function() {
                    // Se não há restrições ou já foi autorizado, não mostra o botão
                    if (empty($this->visitorRestrictions) || $this->authorization_granted) {
                        return false;
                    }
                    
                    // Verifica se há pelo menos uma restrição que não seja de severidade 'none'
                    foreach ($this->visitorRestrictions as $restriction) {
                        if ($restriction->severity_level !== 'none') {
                            return true;
                        }
                    }
                    
                    // Se todas as restrições forem de severidade 'none', não mostra o botão
                    return false;
                })
                ->form(function () {
                    // Contar as restrições por nível de severidade
                    $countLow = 0;
                    $countMedium = 0;
                    $countHigh = 0;
                    
                    foreach ($this->visitorRestrictions as $restriction) {
                        if ($restriction->severity_level === 'low') {
                            $countLow++;
                        } elseif ($restriction->severity_level === 'medium') {
                            $countMedium++;
                        } elseif ($restriction->severity_level === 'high') {
                            $countHigh++;
                        }
                    }
                    
                    // Gerar texto de resumo
                    // $warningText = "<p class='font-bold text-base mb-3'>Atenção:</p>";
                    $warningText = "<p class='mb-3'>Você está liberando as seguintes Restrições de Acesso:</p>";
                    $warningText .= "<ul class='list-disc pl-4 mb-4 space-y-1'>";
                    
                    if ($countLow > 0) {
                        $warningText .= "<li class='text-green-600 dark:text-success-400 font-medium'>{$countLow} de severidade baixa</li>";
                    }
                    
                    if ($countMedium > 0) {
                        $warningText .= "<li class='text-amber-600 dark:text-warning-400 font-medium'>{$countMedium} de severidade média</li>";
                    }
                    
                    if ($countHigh > 0) {
                        $warningText .= "<li class='text-red-600 dark:text-danger-400 font-medium'>{$countHigh} de severidade alta</li>";
                    }
                    
                    $warningText .= "</ul>";
                    $warningText .= "<p class='font-medium text-red-600 dark:text-danger-400'>Assegure-se de ter lido as restrições e concordado antes da aprovação.</p>";
                    
                    return [
                        \Filament\Forms\Components\Section::make('Atenção')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('restriction_warning')
                                    ->label('')  // Removendo o label para melhorar o design
                                    ->content(function () use ($warningText) {
                                        return new \Illuminate\Support\HtmlString(
                                            "<div class='text-sm space-y-2 py-2'>{$warningText}</div>"
                                        );
                                    }),
                            ]),
                            
                        \Filament\Forms\Components\Section::make('Credenciais para Autorização')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('login')
                                    ->label('Login')
                                    ->email()
                                    ->required()
                                    ->extraInputAttributes(['autocomplete' => 'username']),
                                    
                                \Filament\Forms\Components\TextInput::make('password')
                                    ->label('Senha')
                                    ->password()
                                    ->required()
                                    ->extraInputAttributes(['autocomplete' => 'current-password']),
                            ]),
                    ];
                })
                ->action(function (array $data) {
                    // Tenta autenticar o usuário com as credenciais fornecidas
                    if (!\Illuminate\Support\Facades\Auth::validate([
                        'email' => $data['login'], 
                        'password' => $data['password']
                    ])) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Falha na Autorização')
                            ->body('Credenciais inválidas.')
                            ->send();
                        return;
                    }
                    
                    // Obtém o usuário para verificar permissões
                    $user = \App\Models\User::where('email', $data['login'])->first();
                    
                    if (!$user) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Falha na Autorização')
                            ->body('Usuário não encontrado.')
                            ->send();
                        return;
                    }
                    
                    // Determina a permissão necessária com base na severidade
                    $requiredPermission = match ($this->activeRestriction->severity_level) {
                        'low' => 'low_risk_approval',
                        'medium' => 'medium_risk_approval',
                        'high' => 'high_risk_approval',
                        default => 'high_risk_approval',
                    };
                    
                    // Verifica se o usuário tem a permissão necessária
                    $hasPermission = false;
                    
                    // Usa o método adequado dependendo do sistema de permissões
                    if (method_exists($user, 'hasPermissionTo')) {
                        $hasPermission = $user->hasPermissionTo($requiredPermission);
                    } elseif (method_exists($user, 'can')) {
                        $hasPermission = $user->can($requiredPermission);
                    } else {
                        // Fallback para sistemas sem verificação específica
                        $hasPermission = $user->hasRole('admin');
                    }
                    
                    if (!$hasPermission) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Permissão Negada')
                            ->body('Usuário não possui permissão para autorizar este nível de restrição.')
                            ->send();
                        return;
                    }
                    
                    // Se chegou até aqui, tem permissão
                    $this->authorization_granted = true;
                    
                    // Armazena dados do autorizador para a ocorrência
                    $authorizerData = [
                        'authorizer_name' => $user->name,
                        'authorizer_email' => $user->email,
                    ];
                    
                    // Registra uma ocorrência se auto_occurrence estiver habilitado
                    $occurrenceService = new \App\Services\OccurrenceService();
                    
                    // Acessa os dados diretamente das propriedades da classe
                    // (o que já estiver disponível no momento da autorização)
                    $formData = $this->form->getState();
                    
                    // Adiciona dados do autorizador ao formData
                    $formData = array_merge($formData, $authorizerData);
                    
                    // Obtém os dados diretamente dos componentes
                    foreach ($this->form->getFlatComponents() as $component) {
                        if (method_exists($component, 'getName') && method_exists($component, 'getState')) {
                            $name = $component->getName();
                            $value = $component->getState();
                            if ($name && $value && !isset($formData[$name])) {
                                $formData[$name] = $value;
                            }
                        }
                    }
                    
                    // Busca valores de todos os inputs disponíveis no $_POST
                    foreach ($_POST as $key => $value) {
                        if (strpos($key, 'data.') === 0) {
                            $fieldName = str_replace('data.', '', $key);
                            if (!isset($formData[$fieldName]) && !empty($value)) {
                                $formData[$fieldName] = $value;
                            }
                        }
                    }
                    
                    // Log para depuração
                    \Illuminate\Support\Facades\Log::info('Dados completos do formulário para ocorrência', [
                        'formData' => $formData,
                        'rawPost' => $_POST
                    ]);
                    
                    // Busca o visitante novamente se existir
                    $visitor = null;
                    $doc = $formData['doc'] ?? null;
                    $docTypeId = $formData['doc_type_id'] ?? null;
                    
                    if (!empty($doc) && !empty($docTypeId)) {
                        $visitor = \App\Models\Visitor::where('doc', $doc)
                            ->where('doc_type_id', $docTypeId)
                            ->first();
                    }
                    
                    // Busca o destino se estiver definido
                    $destination = null;
                    $destinationId = $formData['destination_id'] ?? null;
                    if (!empty($destinationId)) {
                        $destination = \App\Models\Destination::find($destinationId);
                    }
                    
                    $occurrenceService->registerAuthorizationOccurrence(
                        $visitor,
                        $formData,
                        $this->visitorRestrictions,
                        $destination
                    );
                    
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Autorização Concedida')
                        ->body('A restrição foi autorizada com sucesso!')
                        ->send();
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url(url()->previous()),
        ];
    }

    // Função para buscar o visitante e registrar uma ocorrência automática caso a restrição seja comum
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

        // Verifica se há uma visita em andamento
        $lastVisit = $visitor->visitorLogs()
            ->latest('in_date')
            ->first();

        if ($lastVisit && $lastVisit->out_date === null) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Visita em Andamento')
                ->body("Este visitante já possui uma visita em andamento no local: {$lastVisit->destination->name}")
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
                    ->send();
                $this->halt();
                return $data;
            }
        }

        // Encontra o visitante se já existir
        $visitor = \App\Models\Visitor::where('doc', $doc)
            ->where('doc_type_id', $docTypeId)
            ->with(['docType', 'activeRestrictions'])
            ->first();

        // Limpar as restrições existentes antes de verificar novamente
        $this->visitorRestrictions = [];
        $this->activeRestriction = null;
        
        // 1. VERIFICAR RESTRIÇÕES COMUNS SE O VISITANTE JÁ EXISTE
        if ($visitor) {
            \Illuminate\Support\Facades\Log::info('CreateVisitor: Verificando restrições comuns para visitante existente', [
                'visitor_id' => $visitor->id,
                'doc' => $visitor->doc,
                'name' => $visitor->name,
            ]);

            // Verifica restrições usando o relacionamento
            $activeRestrictions = $visitor->activeRestrictions()->get();

            \Illuminate\Support\Facades\Log::info('CreateVisitor: Resultado da consulta de restrições comuns', [
                'visitor_id' => $visitor->id,
                'count' => $activeRestrictions->count(),
                'restrições' => $activeRestrictions->toArray(),
            ]);
            
            // Achou restrições ativas
            if ($visitor->hasActiveRestrictions() || $activeRestrictions->count() > 0) {
                // Adiciona todas as restrições ativas ao array
                foreach ($activeRestrictions as $restriction) {
                    // Converte a restrição para um formato padrão de objeto
                    $restrictionArray = $restriction instanceof \Illuminate\Database\Eloquent\Model 
                        ? $restriction->toArray() 
                        : (array)$restriction;
                    $restrictionArray['is_predictive'] = false;
                    $restrictionArray['restriction_type'] = 'Restrição Comum';
                    $restrictionObj = (object)$restrictionArray;
                    
                    // Adiciona ao array de restrições
                    $this->visitorRestrictions[] = $restrictionObj;
                    
                    \Illuminate\Support\Facades\Log::info('Restrição comum adicionada ao array durante o save', [
                        'id' => $restrictionObj->id,
                        'tipo' => 'Comum',
                        'reason' => $restrictionObj->reason,
                        'severity' => $restrictionObj->severity_level
                    ]);
                    
                    // Log informando que a restrição comum foi encontrada e poderá gerar uma Ocorrência Automática
                    \Illuminate\Support\Facades\Log::warning('[Ocorrência Automática - VisitorResource]', [
                        'restriction_id' => $restriction->id,
                        'visitor_id' => $visitor->id,
                        'visitor_doc' => $visitor->doc,
                        'visitor_name' => $visitor->name,
                        'visitor_phone' => $visitor->phone ?? 'N/A',
                        'operator_name' => Auth::user()->name,
                        'operator_email' => Auth::user()->email,
                        'date_time' => now()->format('d/m/Y H:i:s'),
                        'occurrence_key' => 'common_visitor_restriction',
                        'occurrence_title' => 'Restrição de Acesso Comum Detectada',
                        'occurrence_description' => 'Cadastro de visitante com Restrição de Acesso Comum',
                        'occurrence_severity_level' => $restriction->severity_level,
                        'occurrence_expires_at_formatted' => $restriction->expires_at ? $restriction->expires_at->format('d/m/Y') : 'Nunca',
                        'occurrence_reason' => $restriction->reason,
                    ]);
                    
                    // Verifica se a restrição está configurada para gerar ocorrência automática
                    // Registra a ocorrência apenas se auto_occurrence estiver habilitado na restrição
                    if ($restriction->auto_occurrence && !$this->authorization_granted) {
                        // Registrar a ocorrência automática
                        $docTypeName = \App\Models\DocType::find($visitor->doc_type_id)?->type ?? 'Desconhecido';
                        
                        $description = "Cadastro de visitante com Restrição de Acesso Comum:

Dados do visitante:
Nome: " . $visitor->name . "
Documento: " . $visitor->doc . " (" . $docTypeName . ")
Telefone: " . ($visitor->phone ?? 'N/A') . "
Destino: " . ($destination ? $destination->name : 'Não informado') . "

Detalhes da restrição:
Motivo: " . $restriction->reason . "
Severidade: " . $restriction->severity_level . "
Operador: " . Auth::user()->name . " - " . Auth::user()->email . "
OBS: Ocorrência gerada automaticamente pelo sistema de monitoramento de visitantes.";

                        $occurrence = \App\Models\Occurrence::create([
                            'description' => $description,
                            'severity' => match ($restriction->severity_level) {
                                'none' => 'gray',
                                'low' => 'green',
                                'medium' => 'amber',
                                'high' => 'red',
                                default => 'gray',
                            },
                            'occurrence_datetime' => now(),
                            'created_by' => Auth::id(),
                            'updated_by' => null,
                            'is_editable' => false,
                        ]);
                        
                        // Vincular o visitante à ocorrência
                        $occurrence->visitors()->attach($visitor->id);
                        
                        // Vincular o destino à ocorrência (se existir)
                        if ($destinationId) {
                            $occurrence->destinations()->attach($destinationId);
                        }
                        
                        \Illuminate\Support\Facades\Log::info('[Ocorrência Automática - VisitorResource] Ocorrência registrada com sucesso', [
                            'key' => 'common_visitor_restriction',
                            'occurrence_id' => $occurrence->id,
                            'visitor_id' => $visitor->id
                        ]);
                    } else {
                        \Illuminate\Support\Facades\Log::info('[Ocorrência Automática - VisitorResource] Ocorrência automática desabilitada na restrição', [
                            'restriction_id' => $restriction->id,
                            'auto_occurrence' => false
                        ]);
                    }
                }
                
                // Se encontrou restrições, define a mais crítica como principal para compatibilidade
                if (count($this->visitorRestrictions) > 0) {
                    // Obtém a restrição mais crítica para compatibilidade
                    $restriction = $visitor->getMostCriticalRestrictionAttribute();
                
                    if (!$restriction && $activeRestrictions->count() > 0) {
                        $restriction = $activeRestrictions->first();
                    }
                
                    if ($restriction) {
                        // Converte para objeto padrão
                        $restrictionArray = $restriction instanceof \Illuminate\Database\Eloquent\Model 
                            ? $restriction->toArray() 
                            : (array)$restriction;
                        $restrictionArray['is_predictive'] = false;
                        $restrictionArray['restriction_type'] = 'Restrição Comum';
                        $restrictionObj = (object)$restrictionArray;
                        
                        // Mantém compatibilidade com código existente
                        $this->activeRestriction = $restrictionObj;
                        
                        \Illuminate\Support\Facades\Log::info('Restrição principal definida para compatibilidade', [
                            'id' => $restrictionObj->id,
                            'count_total' => count($this->visitorRestrictions),
                            'tipo' => 'Comum'
                        ]);
                    }
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
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('Ver Detalhes')
                            ->url(route('filament.dashboard.resources.visitors.edit', $visitor))
                            ->button(),
                    ])
                    ->send();
                $this->halt();
                return $data;
            }
        }
        
        // 2. VERIFICAR RESTRIÇÕES PREDITIVAS PARA TODOS OS VISITANTES
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Verificando restrições preditivas no momento do save', [
            'name' => $formData['name'] ?? null,
            'doc' => $doc,
            'doc_type_id' => $docTypeId,
            'destination_id' => $destinationId
        ]);
        
        // Usa o serviço para verificar restrições preditivas
        $predictiveService = new \App\Services\PredictiveRestrictionService();
        $matchedRestrictions = $predictiveService->checkRestrictions($formData);
        
        if (!empty($matchedRestrictions)) {
            \Illuminate\Support\Facades\Log::info('CreateVisitor: Restrições preditivas encontradas no momento do save', [
                'total' => count($matchedRestrictions)
            ]);
            
            // Adiciona cada restrição encontrada ao array
            foreach ($matchedRestrictions as $restrictionObj) {
                // Adiciona ao array de restrições
                $this->visitorRestrictions[] = $restrictionObj;
                
                // Mantém a compatibilidade com o código existente (principal restrição)
                if ($this->activeRestriction === null || 
                    $this->getSeverityLevel($restrictionObj->severity_level) > $this->getSeverityLevel($this->activeRestriction->severity_level)) {
                    $this->activeRestriction = $restrictionObj;
                }
                
                \Illuminate\Support\Facades\Log::info('Restrição preditiva adicionada ao array durante o save', [
                    'id' => $restrictionObj->id,
                    'tipo' => 'Preditiva',
                    'reason' => $restrictionObj->reason,
                    'severity' => $restrictionObj->severity_level,
                    'count_total' => count($this->visitorRestrictions)
                ]);
                
                // Log informando que a restrição preditiva foi encontrada e poderá gerar uma Ocorrência Automática
                \Illuminate\Support\Facades\Log::warning('[Ocorrência Automática - VisitorResource]', [
                    'restriction_id' => $restrictionObj->id,
                    'visitor_doc' => $formData['doc'] ?? 'N/A',
                    'visitor_name' => $formData['name'] ?? 'N/A',
                    'visitor_phone' => $formData['phone'] ?? 'N/A',
                    'operator_name' => Auth::user()->name,
                    'operator_email' => Auth::user()->email,
                    'date_time' => now()->format('d/m/Y H:i:s'),
                    'occurrence_key' => 'predictive_visitor_restriction',
                    'occurrence_title' => 'Restrição de Acesso Preditiva Detectada',
                    'occurrence_description' => 'Cadastro de visitante com Restrição de Acesso Preditiva',
                    'occurrence_severity_level' => $restrictionObj->severity_level,
                    'occurrence_reason' => $restrictionObj->reason,
                    'occurrence_match_reason' => $restrictionObj->match_reason ?? 'N/A',
                ]);
                // Verifica se a restrição preditiva está configurada para gerar ocorrência automática
                
                // Registra a ocorrência apenas se auto_occurrence estiver habilitado
                if ($restrictionObj->auto_occurrence && !$this->authorization_granted) {
                    // Registrar a ocorrência automática
                    $docTypeName = \App\Models\DocType::find($formData['doc_type_id'])?->type ?? 'Desconhecido';
                    
                    $description = "Cadastro de visitante com Restrição de Acesso Preditiva:

Dados do visitante:
Nome: " . ($formData['name'] ?? 'N/A') . "
Documento: " . ($formData['doc'] ?? 'N/A') . " (" . $docTypeName . ")
Telefone: " . ($formData['phone'] ?? 'N/A') . "
Destino: " . ($destination ? $destination->name : 'Não informado') . "

Detalhes da restrição:
Motivo: " . $restrictionObj->reason . "
Severidade: " . (\App\Models\PredictiveVisitorRestriction::SEVERITY_LEVELS[$restrictionObj->severity_level] ?? $restrictionObj->severity_level) . "
Correspondência: " . ($restrictionObj->match_reason ?? 'N/A') . "
Operador: " . Auth::user()->name . " - " . Auth::user()->email . "
OBS: Ocorrência gerada automaticamente pelo sistema de monitoramento de visitantes.";

                    $occurrence = \App\Models\Occurrence::create([
                        'description' => $description,
                        'severity' => match ($restrictionObj->severity_level) {
                            'none' => 'gray',
                            'low' => 'green',
                            'medium' => 'amber',
                            'high' => 'red',
                            default => 'gray',
                        },
                        'occurrence_datetime' => now(),
                        'created_by' => Auth::id(),
                        'updated_by' => null,
                        'is_editable' => false,
                    ]);
                    
                    // Vincular o visitante à ocorrência (se já existir)
                    if ($visitor) {
                        $occurrence->visitors()->attach($visitor->id);
                    }
                    
                    // Vincular o destino à ocorrência (se existir)
                    if ($destinationId) {
                        $occurrence->destinations()->attach($destinationId);
                    }
                    
                    \Illuminate\Support\Facades\Log::info('[Ocorrência Automática - VisitorResource] Ocorrência preditiva registrada com sucesso', [
                        'key' => 'predictive_visitor_restriction',
                        'occurrence_id' => $occurrence->id,
                        'visitor_id' => $visitor ? $visitor->id : null
                    ]);
                } else {
                    \Illuminate\Support\Facades\Log::info('[Ocorrência Automática - VisitorResource] Ocorrência automática para restrição preditiva desabilitada', [
                        'restriction_id' => $restrictionObj->id,
                        'auto_occurrence' => false
                    ]);
                }
            }
            
            // Notifica o usuário sobre a restrição preditiva
            // if ($this->activeRestriction) {
            //     \Filament\Notifications\Notification::make()
            //         ->warning()
            // }
        }
        
        // 3. VERIFICAR AUTORIZAÇÃO E DECIDIR SE CONTINUA
        // Se existem restrições e não foi autorizado, interrompe a criação
        if ($this->activeRestriction && 
            $this->activeRestriction->severity_level !== 'none' && 
            !$this->authorization_granted) {
            
            \Illuminate\Support\Facades\Log::warning('CreateVisitor: Criação interrompida - restrição não autorizada', [
                'severity' => $this->activeRestriction->severity_level,
                'authorized' => $this->authorization_granted
            ]);
            
            // Notifica o usuário que a ação foi interrompida
            Notification::make()
                ->warning()
                ->title('Autorização Necessária')
                ->body('Este visitante possui restrições que precisam ser autorizadas antes de prosseguir.')
                ->send();
                
            $this->halt();
            return $data;
        }
        
        // Se chegou até aqui, as verificações foram bem-sucedidas ou as restrições foram autorizadas
        
        // Se o visitante já existe, atualiza apenas as informações permitidas
        if ($visitor) {
            // Atualiza o visitante existente
            $visitor->update([
                'phone' => $data['phone'] ?? null,
                'other' => $data['other'] ?? null,
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

    /**
     * Converte o nível de severidade para um valor numérico para comparação
     */
    protected function getSeverityLevel(string $severity): int
    {
        return match ($severity) {
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            default => 0,
        };
    }

    /**
     * Obtém o nome do tipo de documento a partir do ID
     * 
     * @param int|null $docTypeId ID do tipo de documento
     * @return string Nome do tipo de documento ou valor padrão
     */
    protected function getDocumentTypeName($docTypeId)
    {
        if (!$docTypeId) {
            \Illuminate\Support\Facades\Log::info('getDocumentTypeName: ID do tipo de documento não fornecido');
            return 'Não especificado';
        }
        
        try {
            $docType = \App\Models\DocType::find($docTypeId);
            if ($docType) {
                return $docType->type;
            }
            
            \Illuminate\Support\Facades\Log::info('getDocumentTypeName: Tipo de documento não encontrado', [
                'doc_type_id' => $docTypeId
            ]);
            return 'Tipo #' . $docTypeId;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('getDocumentTypeName: Erro ao buscar tipo de documento', [
                'doc_type_id' => $docTypeId,
                'error' => $e->getMessage()
            ]);
            return 'Tipo #' . $docTypeId;
        }
    }
}
