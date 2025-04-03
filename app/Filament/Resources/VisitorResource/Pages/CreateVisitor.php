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
use App\Models\PredictiveVisitorRestriction;
use App\Models\CommonVisitorRestriction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\VisitorResource;
use Filament\Forms\Components\Actions\Action;
use App\Filament\Forms\Components\WebcamCapture;
use App\Filament\Forms\Components\DocumentPhotoCapture;

class CreateVisitor extends CreateRecord
{
    protected static string $resource = VisitorResource::class;

    // protected ?string $maxContentWidth = MaxWidth::Full->value;

    public bool $showAllFields = false;
    public $visitorRestrictions = []; // Array para armazenar todas as restrições aplicáveis
    public $PredictiveVisitorRestriction = null; // Mantida para compatibilidade com código existente
    public $CommonVisitorRestriction = null; // Mantida para compatibilidade com código existente
    public $authorization_granted = false; // Nova propriedade para controlar autorização

    public function mount(): void
    {
        parent::mount();
        
        // Reinicia as propriedades
        $this->visitorRestrictions = [];
        $this->PredictiveVisitorRestriction = null;
        $this->CommonVisitorRestriction = null;
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
                        // Campo de alerta de restrição que aparece apenas quando há restrições
                        Placeholder::make('restriction_alert')
                            ->label(function () {
                                if (empty($this->visitorRestrictions)) {
                                    return null;
                                }

                                // Determina a maior severidade entre todas as restrições
                                $maxSeverity = 'low';
                                foreach ($this->visitorRestrictions as $restriction) {
                                    if ($restriction->severity_level === 'high') {
                                        $maxSeverity = 'high';
                                        break; // Alta severidade é a máxima, podemos parar aqui
                                    } elseif ($restriction->severity_level === 'medium' && $maxSeverity !== 'high') {
                                        $maxSeverity = 'medium';
                                    }
                                }
                                
                                $severityText = match ($maxSeverity) {
                                    'low' => 'Baixa',
                                    'medium' => 'Média',
                                    'high' => 'Alta',
                                    default => 'Desconhecida',
                                };
                                
                                $colorClass = match ($maxSeverity) {
                                    'low' => 'text-green-600 dark:text-success-400 font-bold',
                                    'medium' => 'text-amber-600 dark:text-warning-400 font-bold',
                                    'high' => 'text-red-600 dark:text-danger-400 font-bold',
                                    default => 'text-amber-600 dark:text-warning-400 font-bold',
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
                                if (empty($this->visitorRestrictions)) {
                                    return null;
                                }
                                
                                // Registrar ocorrências para cada restrição - mantido por compatibilidade
                                if (count($this->visitorRestrictions) > 0 && isset($this->visitorRestrictions[0]->is_predictive) && $this->visitorRestrictions[0]->is_predictive) {
                                    // Verifica se a ocorrência automática está habilitada (registro de ocorrência movido para o método principal)
                                    $automaticOccurrence = \App\Models\AutomaticOccurrence::where('key', 'predictive_visitor_restriction')->first();
                                    
                                    \Illuminate\Support\Facades\Log::info('[Ocorrência Automática - VisitorResource] Status da Ocorrência Automática', [
                                        'key' => 'predictive_visitor_restriction',
                                        'enabled' => $automaticOccurrence ? $automaticOccurrence->enabled : false,
                                        'restriction_count' => count($this->visitorRestrictions)
                                    ]);
                                }
                                
                                // Constrói o HTML para exibir todas as restrições
                                $html = "";
                                
                                foreach ($this->visitorRestrictions as $index => $restriction) {
                                    $severityClass = match ($restriction->severity_level) {
                                        'low' => 'text-green-600 dark:text-success-400',
                                        'medium' => 'text-amber-600 dark:text-amber-400',
                                        'high' => 'text-red-600 dark:text-danger-400',
                                        default => 'text-gray-600 dark:text-gray-400',
                                    };
                                    
                                    $borderClass = match ($restriction->severity_level) {
                                        'low' => 'border-green-600 dark:border-success-400',
                                        'medium' => 'border-amber-600 dark:border-amber-400',
                                        'high' => 'border-red-600 dark:border-danger-400',
                                        default => 'border-gray-600 dark:border-gray-400',
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
                                        : (isset($restriction->is_predictive) && $restriction->is_predictive 
                                            ? 'Restrição Preditiva' 
                                            : 'Restrição Comum');
                                    
                                    $html .= "
                                    <div class='p-4 rounded-lg border-2 {$borderClass} mb-3'>
                                        <div class='flex justify-between items-start'>
                                            <h3 class='font-bold {$severityClass}'>Restrição #" . ($index + 1) . " ({$restrictionType})</h3>
                                            <span class='font-medium {$severityClass}'>" . match ($restriction->severity_level) {
                                                'low' => 'Severidade: Baixa',
                                                'medium' => 'Severidade: Média',
                                                'high' => 'Severidade: Alta',
                                                default => 'Severidade: Desconhecida',
                                            } . "</span>
                                        </div>
                                        <p class='my-2 {$severityClass}'>{$restriction->reason}</p>
                                        <div class='flex flex-col sm:flex-row sm:gap-4 text-sm'>
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
                            ->visible(fn () => !empty($this->visitorRestrictions))
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
                    ->label('Visitante com Restrição')
                    ->content(function() {
                        if (!$this->PredictiveVisitorRestriction) {
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
                        
                        $colorClass = match ($this->PredictiveVisitorRestriction->severity_level) {
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
                    ->visible(fn() => $this->PredictiveVisitorRestriction !== null)
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
        return [
            $this->getCreateFormAction()
                ->label('Salvar dados do Visitante')
                ->color('success')
                ->icon('heroicon-o-printer')
                ->visible(fn () => true) // Sempre visível
                ->disabled(fn() => ($this->PredictiveVisitorRestriction !== null && !$this->authorization_granted) || !$this->showAllFields)
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
                                // ->persistent()
                                ->send();
                            return;
                        }
                    }

                    // Verifica restrições parciais antes de salvar
                    $this->checkPredictiveRestrictions($formData);
                    
                    // Se não houver restrições parciais ou já foram autorizadas, cria o visitante
                    if (!$this->PredictiveVisitorRestriction || $this->authorization_granted) {
                    $this->create();
                    }
                }),

            Actions\Action::make('authorize_restriction')
                ->label(fn() => count($this->visitorRestrictions) > 1 ? 'Autorizar Restrições' : 'Autorizar Restrição')
                ->color('warning')
                ->icon('heroicon-o-key')
                ->visible(fn() => !empty($this->visitorRestrictions) && !$this->authorization_granted)
                ->form(function () {
                    $restrictionInfo = '';
                    $expirationInfo = '';
                    
                    if ($this->PredictiveVisitorRestriction) {
                        $restrictionInfo = "Restrição: {$this->PredictiveVisitorRestriction->reason}";
                        
                        if ($this->PredictiveVisitorRestriction->expires_at) {
                            $expirationInfo = "Expira em: " . (is_object($this->PredictiveVisitorRestriction->expires_at) ? 
                                $this->PredictiveVisitorRestriction->expires_at->format('d/m/Y') : 
                                date('d/m/Y', strtotime($this->PredictiveVisitorRestriction->expires_at)));
                        }
                    }
                    
                    return [
                        \Filament\Forms\Components\Section::make('Detalhes da Restrição')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('restriction_type')
                                    ->label('Tipo de Restrição')
                                    ->content(function () {
                                        if (!$this->PredictiveVisitorRestriction) {
                                            return '-';
                                        }
                                        
                                        // Verificamos várias características para determinar o tipo de restrição
                                        $isPredictive = false;
                                        
                                        // Se tiver o atributo is_predictive, usamos ele diretamente
                                        if (isset($this->PredictiveVisitorRestriction->is_predictive)) {
                                            $isPredictive = $this->PredictiveVisitorRestriction->is_predictive;
                                        } 
                                        // Se tiver atributos específicos de restrição preditiva
                                        elseif (isset($this->PredictiveVisitorRestriction->predictive_doc) || 
                                               isset($this->PredictiveVisitorRestriction->predictive_name) ||
                                               isset($this->PredictiveVisitorRestriction->partial_doc) || 
                                               isset($this->PredictiveVisitorRestriction->partial_name)) {
                                            $isPredictive = true;
                                        }
                                        // Se for uma instância do modelo CommonVisitorRestriction
                                        elseif ($this->PredictiveVisitorRestriction instanceof \App\Models\CommonVisitorRestriction) {
                                            $isPredictive = false;
                                        }
                                        // Se for uma instância do modelo PredictiveVisitorRestriction
                                        elseif ($this->PredictiveVisitorRestriction instanceof \App\Models\PredictiveVisitorRestriction) {
                                            $isPredictive = true;
                                        }
                                        
                                        $type = $isPredictive ? 'Restrição Preditiva' : 'Restrição Comum';
                                        
                                        // Adiciona logs para debug
                                        \Illuminate\Support\Facades\Log::info('Tipo de restrição determinado', [
                                            'tipo' => $type,
                                            'is_predictive' => $isPredictive,
                                            'classe' => get_class($this->PredictiveVisitorRestriction),
                                            'atributos' => array_keys((array)$this->PredictiveVisitorRestriction)
                                        ]);
                                        
                                        return $type;
                                    }),
                                    
                                \Filament\Forms\Components\Placeholder::make('severity_level')
                                    ->label('Nível de Severidade')
                                    ->content(function () {
                                        if (!$this->PredictiveVisitorRestriction) {
                                            return '-';
                                        }
                                        
                                        $severityClass = match ($this->PredictiveVisitorRestriction->severity_level) {
                                            'low' => 'text-green-600',
                                            'medium' => 'text-amber-600',
                                            'high' => 'text-red-600',
                                            default => 'text-gray-600',
                                        };
                                        
                                        $severityText = match ($this->PredictiveVisitorRestriction->severity_level) {
                                            'low' => 'Baixa',
                                            'medium' => 'Média',
                                            'high' => 'Alta',
                                            default => 'Desconhecida',
                                        };
                                        
                                        return new \Illuminate\Support\HtmlString(
                                            "<span class='{$severityClass} font-medium'>{$severityText}</span>"
                                        );
                                    }),
                                    
                                \Filament\Forms\Components\Placeholder::make('restriction_reason')
                                    ->label('Motivo da Restrição')
                                    ->content(function () {
                                        if (!$this->PredictiveVisitorRestriction) {
                                            return '-';
                                        }

                                        $severityClass = match ($this->PredictiveVisitorRestriction->severity_level) {
                                            'low' => 'text-green-600',
                                            'medium' => 'text-amber-600',
                                            'high' => 'text-red-600',
                                            default => 'text-gray-600',
                                        };

                                        return new \Illuminate\Support\HtmlString(
                                            "<span class='{$severityClass}'>{$this->PredictiveVisitorRestriction->reason}</span>"
                                        );
                                    }),
                                    
                                \Filament\Forms\Components\Placeholder::make('restriction_expiration')
                                    ->label('Data de Expiração')
                                    ->content(function () {
                                        if (!$this->PredictiveVisitorRestriction) {
                                            return '-';
                                        }

                                        $severityClass = match ($this->PredictiveVisitorRestriction->severity_level) {
                                            'low' => 'text-green-600',
                                            'medium' => 'text-amber-600',
                                            'high' => 'text-red-600',
                                            default => 'text-gray-600',
                                        };

                                        $expirationText = $this->PredictiveVisitorRestriction->expires_at 
                                            ? (is_object($this->PredictiveVisitorRestriction->expires_at) ? 
                                                $this->PredictiveVisitorRestriction->expires_at->format('d/m/Y') : 
                                                date('d/m/Y', strtotime($this->PredictiveVisitorRestriction->expires_at)))
                                            : 'Sem data de expiração';

                                        return new \Illuminate\Support\HtmlString(
                                            "<span class='{$severityClass}'>{$expirationText}</span>"
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
                    $requiredPermission = match ($this->PredictiveVisitorRestriction->severity_level) {
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

        // Verifica restrições usando o relacionamento
        $activeRestrictions = $visitor->activeRestrictions()->get();

        \Illuminate\Support\Facades\Log::info('CreateVisitor: Resultado da consulta de restrições', [
            'visitor_id' => $visitor->id,
            'count' => $activeRestrictions->count(),
            'restrições' => $activeRestrictions->toArray(),
        ]);

        if ($visitor->hasActiveRestrictions() || $activeRestrictions->count() > 0) {
            // Limpa o array de restrições
            $this->visitorRestrictions = [];
            
            // Adiciona todas as restrições ativas ao array
            foreach ($activeRestrictions as $restriction) {
                // Converte a restrição para um formato padrão de objeto
                $restrictionArray = $restriction->toArray();
                $restrictionArray['is_predictive'] = false;
                $restrictionArray['restriction_type'] = 'Restrição Comum';
                $restrictionObj = (object)$restrictionArray;
                
                // Adiciona ao array de restrições
                $this->visitorRestrictions[] = $restrictionObj;
                
                \Illuminate\Support\Facades\Log::info('Restrição comum adicionada ao array', [
                    'id' => $restrictionObj->id,
                    'tipo' => 'Comum',
                    'reason' => $restrictionObj->reason,
                    'severity' => $restrictionObj->severity_level
                ]);
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
                    $restrictionArray = $restriction->toArray();
                    $restrictionArray['is_predictive'] = false;
                    $restrictionArray['restriction_type'] = 'Restrição Comum';
                    $restrictionObj = (object)$restrictionArray;
                    
                    // Mantém compatibilidade com código existente
                    $this->PredictiveVisitorRestriction = $restrictionObj;
                    
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
                // ->persistent()
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

        // Verifica restrições parciais (garantindo que seja verificado aqui também)
        if (!$this->PredictiveVisitorRestriction) {
            \Illuminate\Support\Facades\Log::info('CreateVisitor: Verificando restrições preditivas no mutateFormDataBeforeCreate');
            $this->checkPredictiveRestrictions($formData);
            
            // Se encontrou uma restrição e não está autorizada, interrompe o processo
            if ($this->PredictiveVisitorRestriction && !$this->authorization_granted) {
                \Illuminate\Support\Facades\Log::warning('CreateVisitor: Restrição preditiva encontrada e não autorizada - interrompendo criação');
                
                Notification::make()
                    ->warning()
                    ->title('Restrição Detectada')
                    ->body('Este visitante possui uma restrição que precisa ser autorizada antes de prosseguir.')
                    ->send();
                    
                $this->halt();
                return $data;
            }
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
                    // ->persistent()
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
                'phone' => $data['phone'] ?? null,
                'destination_id' => $data['destination_id']
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
     * Verifica se existem restrições preditivas que se aplicam ao visitante
     */
    protected function checkPredictiveRestrictions(array $formData): void
    {
        // Se já há uma restrição específica de visitante, não precisa verificar preditivas
        if ($this->PredictiveVisitorRestriction !== null) {
            \Illuminate\Support\Facades\Log::warning('CreateVisitor: Verificação de restrições preditivas interrompida - já existe uma restrição ativa', [
                'current_restriction' => $this->PredictiveVisitorRestriction
            ]);
            return;
        }
        
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Iniciando verificação de restrições preditivas', [
            'doc' => $formData['doc'] ?? null,
            'name' => mb_strtoupper($formData['name'] ?? ''),
            'phone' => $formData['phone'] ?? null,
            'doc_type_id' => $formData['doc_type_id'] ?? null
        ]);
        
        // Busca restrições parciais ativas na tabela correta
        $query = \App\Models\PredictiveVisitorRestriction::query()
            ->where('active', true)
            ->where(function ($query) {
                // Restrições sem data de expiração ou com data futura
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
            
        // Log da consulta SQL
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Query de restrições preditivas', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'class' => get_class($query->getModel())
        ]);
            
        $predictiveRestrictions = $query->get();
            
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Restrições preditivas encontradas no banco', [
            'total' => $predictiveRestrictions->count(),
            'restricoes' => $predictiveRestrictions->map(function($r) {
                return [
                    'id' => $r->id,
                    'doc_type_id' => $r->doc_type_id,
                    'predictive_doc' => $r->partial_doc,
                    'predictive_name' => $r->partial_name,
                    'phone' => $r->phone,
                    'active' => $r->active,
                    'expires_at' => $r->expires_at,
                    'severity_level' => $r->severity_level
                ];
            })->toArray()
        ]);
            
        if ($predictiveRestrictions->isEmpty()) {
            \Illuminate\Support\Facades\Log::info('CreateVisitor: Nenhuma restrição preditiva ativa encontrada');
            return;
        }
        
        // Valores do visitante para comparação (convertendo para uppercase para garantir case-insensitive)
        $visitorDoc = $formData['doc'] ?? '';
        $visitorName = mb_strtoupper($formData['name'] ?? '');
        $visitorPhone = $formData['phone'] ?? '';
        $visitorDocTypeId = $formData['doc_type_id'] ?? null;
        
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Dados normalizados do visitante para comparação', [
            'visitorDoc' => $visitorDoc,
            'visitorName' => $visitorName,
            'visitorPhone' => $visitorPhone,
            'visitorDocTypeId' => $visitorDocTypeId
        ]);
        
        foreach ($predictiveRestrictions as $restriction) {
            $matches = false;
            $matchReason = [];
            
            // Normaliza os valores da restrição para comparação (uppercase)
            $restrictionDoc = $restriction->partial_doc;
            $restrictionName = mb_strtoupper($restriction->partial_name ?? '');
            $restrictionPhone = $restriction->phone;
            
            \Illuminate\Support\Facades\Log::info('CreateVisitor: Analisando restrição', [
                'restriction_id' => $restriction->id,
                'doc_type_id' => $restriction->doc_type_id,
                'predictive_doc' => $restrictionDoc,
                'predictive_name' => $restrictionName,
                'phone' => $restrictionPhone
            ]);
            
            // Verifica se o tipo de documento corresponde (ou é nulo = qualquer tipo)
            if ($restriction->doc_type_id !== null && $restriction->doc_type_id != $visitorDocTypeId) {
                \Illuminate\Support\Facades\Log::info('CreateVisitor: Restrição ignorada - tipo de documento não corresponde', [
                    'restriction_doc_type' => $restriction->doc_type_id,
                    'visitor_doc_type' => $visitorDocTypeId
                ]);
                continue;
            }
            
            // Verifica documento com pattern matching
            if ($restrictionDoc && $visitorDoc) {
                $pattern = $this->wildcardToRegex($restrictionDoc);
                \Illuminate\Support\Facades\Log::info('CreateVisitor: Verificando documento', [
                    'pattern' => $pattern,
                    'visitorDoc' => $visitorDoc,
                    'restriction_doc' => $restrictionDoc,
                    'exact_match' => (!str_contains($restrictionDoc, '*') && !str_contains($restrictionDoc, '?'))
                ]);
                
                // Se não há wildcards, a correspondência deve ser exata (não parcial)
                if (preg_match($pattern, $visitorDoc)) {
                    $matches = true;
                    $matchReason[] = 'documento';
                    \Illuminate\Support\Facades\Log::info('CreateVisitor: Match no documento');
                }
            }
            
            // Verifica nome com pattern matching
            if ($restrictionName && $visitorName) {
                $pattern = $this->wildcardToRegex($restrictionName);
                \Illuminate\Support\Facades\Log::info('CreateVisitor: Verificando nome', [
                    'pattern' => $pattern,
                    'visitorName' => $visitorName,
                    'restriction_name' => $restrictionName,
                    'exact_match' => (!str_contains($restrictionName, '*') && !str_contains($restrictionName, '?'))
                ]);
                
                // Se não há wildcards, a correspondência deve ser exata (não parcial)
                // Ex: "EDUARDO MELO" só corresponde a "EDUARDO MELO", não a "JUCA MELO"
                if (preg_match($pattern, $visitorName)) {
                    $matches = true;
                    $matchReason[] = 'nome';
                    \Illuminate\Support\Facades\Log::info('CreateVisitor: Match no nome');
                }
            }
            
            // Verifica telefone com pattern matching
            if ($restrictionPhone && $visitorPhone) {
                $pattern = $this->wildcardToRegex($restrictionPhone);
                \Illuminate\Support\Facades\Log::info('CreateVisitor: Verificando telefone', [
                    'pattern' => $pattern,
                    'visitorPhone' => $visitorPhone,
                    'restriction_phone' => $restrictionPhone,
                    'exact_match' => (!str_contains($restrictionPhone, '*') && !str_contains($restrictionPhone, '?'))
                ]);
                
                // Se não há wildcards, a correspondência deve ser exata (não parcial)
                if (preg_match($pattern, $visitorPhone)) {
                    $matches = true;
                    $matchReason[] = 'telefone';
                    \Illuminate\Support\Facades\Log::info('CreateVisitor: Match no telefone');
                }
            }
            
            // Comparação direta para debug
            if ($restrictionName && $visitorName) {
                \Illuminate\Support\Facades\Log::info('CreateVisitor: Comparação direta de nomes', [
                    'restrictionName' => $restrictionName,
                    'visitorName' => $visitorName,
                    'são_iguais' => $restrictionName === $visitorName,
                    'strpos' => strpos($visitorName, $restrictionName) !== false
                ]);
            }
            
            // Se corresponder a qualquer critério, define a restrição
            if ($matches) {
                \Illuminate\Support\Facades\Log::warning('CreateVisitor: Restrição preditiva encontrada', [
                    'restriction_id' => $restriction->id,
                    'match_fields' => $matchReason,
                    'predictive_doc' => $restriction->partial_doc,
                    'predictive_name' => $restriction->partial_name,
                    'phone' => $restriction->phone,
                    'severity_level' => $restriction->severity_level,
                ]);
                
                // Criamos um objeto com os dados da restrição preditiva
                $restrictionObj = (object) [
                    'id' => $restriction->id,
                    'reason' => $restriction->reason,
                    'severity_level' => $restriction->severity_level,
                    'expires_at' => $restriction->expires_at,
                    'is_predictive' => true,
                    'restriction_type' => 'Restrição Preditiva',
                    // Campos adicionais que podem ser úteis
                    'predictive_doc' => $restriction->partial_doc,
                    'predictive_name' => $restriction->partial_name,
                    'phone' => $restriction->phone,
                    'doc' => $visitorDoc,
                    'docType' => (object) ['type' => \App\Models\DocType::find($visitorDocTypeId)?->type ?? 'Desconhecido'],
                    'name' => $visitorName,
                    'match_reason' => implode(', ', $matchReason),
                ];
                
                // Adiciona ao array de restrições
                $this->visitorRestrictions[] = $restrictionObj;
                
                // Mantém a compatibilidade com o código existente (principal restrição)
                $this->PredictiveVisitorRestriction = $restrictionObj;
                
                \Illuminate\Support\Facades\Log::info('Restrição preditiva adicionada ao array', [
                    'id' => $restrictionObj->id,
                    'tipo' => 'Preditiva',
                    'reason' => $restrictionObj->reason,
                    'severity' => $restrictionObj->severity_level,
                    'count_total' => count($this->visitorRestrictions)
                ]);
                
                // Log informando que a restrição preditiva foi encontrada e será gerada uma Ocorrência Automática
                \Illuminate\Support\Facades\Log::warning('[Ocorrência Automática - VisitorResource]', [
                    'restriction_id' => $restriction->id,
                    'restriction_predictive_name' => $restriction->partial_name,
                    'restriction_predictive_doc' => $restriction->partial_doc,
                    'restriction_phone' => $restriction->phone,
                    'visitor_doc' => $visitorDoc,
                    'visitor_name' => $visitorName,
                    'visitor_phone' => $visitorPhone,
                    'visitor_doc_type_id' => $visitorDocTypeId,
                    'match_reason' => $matchReason,
                    'operator_name' => Auth::user()->name,
                    'operator_email' => Auth::user()->email,
                    'date_time' => now()->format('d/m/Y H:i:s'),
                    'occurrence_key' => 'predictive_visitor_restriction',
                    'occurrence_title' => 'Restrição de Acesso Preditiva Detectada',
                    'occurrence_description' => 'Registro de tentativa de cadastro de visitante que corresponde a uma Restrição de Acesso Preditiva',
                    'occurrence_severity_level' => $restriction->severity_level,
                    'occurrence_expires_at_formatted' => $restriction->expires_at ? $restriction->expires_at->format('d/m/Y') : 'Nunca',
                    'occurrence_reason' => $restriction->reason,
                ]);
                
                // Verifica se a ocorrência automática está habilitada
                $automaticOccurrence = \App\Models\AutomaticOccurrence::where('key', 'predictive_visitor_restriction')->first();
                
                // Registra a ocorrência apenas se estiver habilitada
                if ($automaticOccurrence && $automaticOccurrence->enabled) {
                    // Registrar a ocorrência automática
                    $matchReasonText = implode(', ', $matchReason);
                    $docTypeName = \App\Models\DocType::find($visitorDocTypeId)?->type ?? 'Desconhecido';
                    
                    $occurrence = \App\Models\Occurrence::create([
                        'description' => "Registro de tentativa de cadastro de visitante que corresponde a uma Restrição de Acesso Preditiva:\n\nDados do visitante:\nNome: {$visitorName}\nDocumento: {$visitorDoc} ({$docTypeName})\nTelefone: {$visitorPhone}\n\nDetalhes da restrição:\nParâmetros que corresponderam: {$matchReasonText}\nMotivo: {$restriction->reason}\nRegistrado por: " . Auth::user()->name . " - " . Auth::user()->email . "\n\nOcorrência gerada automaticamente pelo sistema de monitoramento de visitantes.",
                        'severity' => match ($restriction->severity_level) {
                            'low' => 'green',
                            'medium' => 'amber',
                            'high' => 'red',
                            default => 'amber',
                        },
                        'occurrence_datetime' => now(),
                        'created_by' => Auth::id(),
                        'updated_by' => null,
                    ]);

                    // Buscar o visitante pelo documento (pode não existir ainda, já que está em processo de criação)
                    $formData = $this->form->getRawState();
                    $visitor = \App\Models\Visitor::where('doc', $formData['doc'])
                        ->where('doc_type_id', $formData['doc_type_id'])
                        ->first();

                    // Vincular o visitante à ocorrência se ele existir
                    if ($visitor) {
                        $occurrence->visitors()->attach($visitor->id);
                    }

                    // Vincular o destino à ocorrência (se existir)
                    if (!empty($formData['destination_id'])) {
                        $occurrence->destinations()->attach($formData['destination_id']);
                    }
                } else {
                    \Illuminate\Support\Facades\Log::info('[Ocorrência Automática - VisitorResource] Ocorrência automática desabilitada', [
                        'key' => 'predictive_visitor_restriction',
                        'enabled' => $automaticOccurrence ? $automaticOccurrence->enabled : false
                    ]);
                }
                
                // Determina o tipo de notificação baseado na severidade
                $notificationType = match ($restriction->severity_level) {
                    'low' => 'success',
                    'medium' => 'warning',
                    'high' => 'danger',
                    default => 'warning',
                };
                
                break;
            } else {
                \Illuminate\Support\Facades\Log::info('CreateVisitor: Nenhum match encontrado para esta restrição');
            }
        }
        
        if (!$this->PredictiveVisitorRestriction) {
            \Illuminate\Support\Facades\Log::info('CreateVisitor: Nenhuma restrição preditiva aplicável encontrada');
        }
    }
    
    /**
     * Converte padrões com wildcards (* e ?) para regex
     * Segue as regras de conversão:
     * - * (asterisco): representa qualquer quantidade de caracteres (inclusive zero)
     * - ? (interrogação): representa exatamente um caractere
     * 
     * Exemplos:
     * - "EDUARDO MELO" => "^EDUARDO MELO$" (correspondência exata)
     * - "EDUARDO * MELO" => "^EDUARDO .* MELO$" (começa com EDUARDO, termina com MELO)
     * - "* MELO" => ".* MELO$" (termina com MELO)
     * - "EDUARDO *" => "^EDUARDO .*" (começa com EDUARDO)
     * - "*" => ".*" (corresponde a qualquer coisa)
     * - "EDUARDO M?LO" => "^EDUARDO M.LO$" (? = um caractere qualquer)
     */
    protected function wildcardToRegex(string $pattern): string
    {
        $originalPattern = $pattern;
        
        // Escapar caracteres especiais do regex, exceto * e ?
        $pattern = preg_quote($pattern, '/');
        
        // Reverter o escape dos * e ? que queremos processar
        $pattern = str_replace(['\*', '\?'], ['*', '?'], $pattern);
        
        // Converter * para .* (qualquer quantidade de caracteres)
        $pattern = str_replace('*', '.*', $pattern);
        
        // Converter ? para . (exatamente um caractere)
        $pattern = str_replace('?', '.', $pattern);
        
        // Aplicar âncoras de início e fim apenas se o padrão não começa ou termina com *
        $needsStartAnchor = !str_starts_with($originalPattern, '*');
        $needsEndAnchor = !str_ends_with($originalPattern, '*');
        
        $finalPattern = '/';
        if ($needsStartAnchor) {
            $finalPattern .= '^';
        }
        
        $finalPattern .= $pattern;
        
        if ($needsEndAnchor) {
            $finalPattern .= '$';
        }
        
        $finalPattern .= '/i'; // case insensitive
        
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Conversão de wildcard para regex', [
            'original' => $originalPattern,
            'final' => $finalPattern,
            'needs_start' => $needsStartAnchor,
            'needs_end' => $needsEndAnchor
        ]);
        
        return $finalPattern;
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
