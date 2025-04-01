<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="pt-BR">
    <meta http-equiv="Accept-Language" content="pt-BR">
    <title>Relatório de Visitantes</title>
    <style>
        @page {
            margin-bottom: 50px;
            counter-increment: page;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 0 0 60px 0; /* Aumentar padding inferior para o footer */
            counter-reset: page;
        }
        .header {
            position: relative;
            margin-bottom: 20px;
            padding: 10px;
            border-bottom: 1px solid #ccc;
            height: 70px;
        }
        .header-left {
            position: absolute;
            left: 0;
            top: 10px;
        }
        .header-left img {
            height: 50px;
        }
        .header-center {
            text-align: center;
            margin-top: 5px;
        }
        .header-center h2 {
            margin: 0;
            font-size: 18px;
            color: #2563eb;
        }
        .header-center p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #666;
        }
        .header-right {
            position: absolute;
            right: 0;
            top: 15px;
            text-align: right;
            font-size: 10px;
            color: #666;
        }
        .filter-info {
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
            padding: 10px;
            border-radius: 5px;
            background-color: #f9fafb;
        }
        .filter-info h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 14px;
            color: #374151;
        }
        .filter-columns {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .filter-column {
            width: 32%;
            box-sizing: border-box;
        }
        .filter-item {
            margin-bottom: 2px;
        }
        .filter-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .filter-value {
            font-size: 11px;
        }
        .results-container {
            margin-bottom: 50px; /* Espaço para o footer */
        }
        .results {
            width: 100%;
            border-collapse: collapse;
        }
        .results th {
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 6px;
            font-size: 12px;
            text-align: left;
            color: #374151;
        }
        .results td {
            border: 1px solid #e5e7eb;
            padding: 6px;
            font-size: 11px;
            color: #4b5563;
        }
        .results tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border: 1px solid #ffeeba;
            text-align: center;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background-color: #fff;
            border-top: 1px solid #ccc;
            padding: 5px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #666;
            z-index: 1000; /* Garantir que fique acima da tabela */
        }
        .footer-left {
            flex: 1;
            text-align: left;
        }
        .footer-center {
            flex: 1;
            text-align: center;
        }
        .footer-right {
            flex: 1;
            text-align: right;
        }
        .pagenum {
            font-weight: bold;
        }
        
        /* CSS para o rodapé de página, agora gerenciado por HTML */
        .page-number {
            font-weight: bold;
        }
        
        /* Estilo para quebra de página no CSS */
        @media print {
            .page-break {
                page-break-after: always;
            }
            
            /* Garantir que a tabela não quebre células entre páginas */
            .results tbody tr {
                page-break-inside: avoid;
            }
            
            /* Repetir cabeçalho da tabela em cada página */
            .results thead {
                display: table-header-group;
            }
            
            /* Evitar que o cabeçalho e a tabela fiquem em páginas diferentes */
            .header, .filter-info {
                page-break-inside: avoid;
            }
        }
    </style>
    
    <script>
        // Script para adicionar numeração de páginas
        // document.addEventListener('DOMContentLoaded', function() {
        //     // Garantir que o script seja executado após a renderização da página
        //     setTimeout(function() {
        //         // Adicionar um elemento para mostrar o número da página no rodapé
        //         var footers = document.querySelectorAll('.footer');
        //         footers.forEach(function(footer, index) {
        //             var pageNum = document.createElement('div');
        //             pageNum.id = 'page-counter';
        //             pageNum.textContent = 'Página ' + (index + 1);
        //             footer.appendChild(pageNum);
        //         });
        //     }, 100);
        // });
    </script>
</head>
<body>
    <div class="content">
        <div class="header">
            <div class="header-left">
                @if(isset($logoBase64) && !empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="CMRJ">
                @else
                    <img src="{{ public_path('images/logo-cmrj-horizontal.jpg') }}" alt="CMRJ">
                @endif
            </div>
            <div class="header-center">
                <h2>RELATÓRIO DE VISITANTES - GUARDIAN</h2>
                <p>Gerado em: {{ $filterInfo['generated_at'] }} por {{ $filterInfo['generated_by'] }}</p>
            </div>
            <div class="header-right">
                DSL - Diretoria de Segurança do Legislativo
            </div>
        </div>
        
        <div class="filter-info">
            <h3>Critérios de Pesquisa</h3>
            <div class="filter-columns">
                <div class="filter-column">
                    <div class="filter-item">
                        <span class="filter-label">Período:</span>
                        <span class="filter-value">{{ $filterInfo['start_date'] }} às {{ $filterInfo['start_time'] }} até {{ $filterInfo['end_date'] }} às {{ $filterInfo['end_time'] }}</span>
                    </div>
                    <div class="filter-item">
                        <span class="filter-label">Visitante:</span>
                        <span class="filter-value">{{ $filterInfo['visitor_name'] }}</span>
                    </div>
                </div>
                <div class="filter-column">
                    <div class="filter-item">
                        <span class="filter-label">Tipo Documento:</span>
                        <span class="filter-value">{{ $filterInfo['doc_type'] }}</span>
                    </div>
                    <div class="filter-item">
                        <span class="filter-label">Num. Documento:</span>
                        <span class="filter-value">{{ $filterInfo['doc'] }}</span>
                    </div>
                    <div class="filter-item">
                        <span class="filter-label">Destino:</span>
                        <span class="filter-value">{{ $filterInfo['destination'] }}</span>
                    </div>
                </div>
                <div class="filter-column">
                    <div class="filter-item">
                        <span class="filter-label">Total de Registros:</span>
                        <span class="filter-value">{{ $filterInfo['total_records'] }}</span>
                    </div>
                    <div class="filter-item">
                        <span class="filter-label">Ordenado por:</span>
                        <span class="filter-value">{{ $filterInfo['sort_field'] }} ({{ $filterInfo['sort_direction'] }})</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="results-container">
            @if(count($results) > 0)
                <table class="results">
                    <thead>
                        <tr>
                            <th>Nome do Visitante</th>
                            <th>Documento</th>
                            <th>Destino</th>
                            <th>Data de Entrada</th>
                            <th>Data de Saída</th>
                            <th>Duração</th>
                            <th>Operador</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $log)
                            <tr>
                                <td>{{ $log->visitor->name ?? 'N/A' }}</td>
                                <td>{{ ($log->visitor->docType->type ?? '') . ' ' . ($log->visitor->doc ?? 'N/A') }}</td>
                                <td>{{ $log->destination->name ?? 'N/A' }}</td>
                                <td>{{ $log->formatted_in_date }}</td>
                                <td>{{ $log->formatted_out_date }}</td>
                                <td>
                                    @if(!empty($log->in_date) && !empty($log->out_date))
                                        @php
                                            $inDate = new DateTime($log->in_date);
                                            $outDate = new DateTime($log->out_date);
                                            $interval = $inDate->diff($outDate);
                                            
                                            $dias = $interval->days;
                                            $horas = $interval->h;
                                            $minutos = $interval->i;
                                            $segundos = $interval->s;
                                            
                                            if ($dias > 0) {
                                                echo $dias.'d '.$horas.'h';
                                            } elseif ($horas > 0) {
                                                echo $horas.'h '.$minutos.'m';
                                            } else {
                                                echo $minutos.'m '.$segundos.'s';
                                            }
                                        @endphp
                                    @else
                                        Em andamento
                                    @endif
                                </td>
                                <td>{{ $log->operator->name ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="warning">Nenhum registro encontrado para os critérios especificados.</p>
            @endif
        </div>
    </div>
    
    @if(!isset($showFooter) || $showFooter !== false)
    <div class="footer">
        <div class="footer-left">
            DTI - Diretoria de Tecnologia da Informação
        </div>
        <div class="footer-center">
            Sistema Guardian - Relatório de Visitantes
        </div>
        <div class="footer-right">
            <span class="pageNumber"></span> de <span class="totalPages"></span>
        </div>
    </div>
    @endif
</body>
</html> 